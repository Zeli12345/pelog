using System.Diagnostics;
using System.Globalization;
using System.Text;

namespace BalilogKiosk.App.Services;

/// <summary>Jaringan Wi-Fi hasil pemindaian (satu entri per SSID, sinyal terkuat).</summary>
internal sealed record WifiNetwork(string Ssid, string Security, int SignalPercent)
{
    public bool IsOpen =>
        string.IsNullOrWhiteSpace(Security) ||
        Security.Contains("open", StringComparison.OrdinalIgnoreCase) ||
        Security.Contains("terbuka", StringComparison.OrdinalIgnoreCase) ||
        Security.Contains("none", StringComparison.OrdinalIgnoreCase);

    public string SignalText => SignalPercent > 0 ? $"{SignalPercent}%" : "—";

    public string SecurityText => string.IsNullOrWhiteSpace(Security) ? "Terbuka" : Security;
}

/// <summary>
/// Pembungkus netsh (WLAN AutoConfig) supaya layar kunci kiosk dapat menampilkan
/// dan menyambungkan Wi-Fi tanpa keluar dari aplikasi.
///
/// Berjalan sebagai akun kiosk biasa: profil jaringan baru dibuat sebagai
/// profil PENGGUNA (user=current) sehingga tidak memerlukan hak administrator.
/// </summary>
internal static class WifiService
{
    private const int TimeoutMs = 20000;

    public static List<WifiNetwork> ScanNetworks()
    {
        var networks = new List<WifiNetwork>();
        var output = RunNetsh("wlan show networks mode=bssid");

        string? ssid = null;
        var security = string.Empty;
        var signal = 0;

        void Flush()
        {
            if (ssid is not null)
            {
                networks.Add(new WifiNetwork(ssid, security, signal));
            }

            security = string.Empty;
            signal = 0;
        }

        foreach (var raw in output.Split('\n'))
        {
            var line = raw.Trim();
            var separator = line.IndexOf(':');

            if (separator < 0)
            {
                continue;
            }

            var key = line[..separator].Trim();
            var value = line[(separator + 1)..].Trim();

            // "SSID 1 : Nama" (baris BSSID tidak cocok karena diawali "BSSID").
            if (key.StartsWith("SSID", StringComparison.OrdinalIgnoreCase))
            {
                Flush();
                ssid = Sanitize(value);
                continue;
            }

            if (ssid is null)
            {
                continue;
            }

            if (key.StartsWith("auth", StringComparison.OrdinalIgnoreCase) ||
                key.StartsWith("autent", StringComparison.OrdinalIgnoreCase))
            {
                security = Sanitize(value);
            }
            else if (value.EndsWith('%'))
            {
                var digits = new string(value.Where(char.IsDigit).ToArray());

                if (int.TryParse(digits, NumberStyles.Integer, CultureInfo.InvariantCulture, out var percent))
                {
                    signal = Math.Max(signal, Math.Clamp(percent, 0, 100));
                }
            }
        }

        Flush();

        return networks
            .Where(n => n.Ssid.Length > 0)
            .GroupBy(n => n.Ssid, StringComparer.OrdinalIgnoreCase)
            .Select(group => group.OrderByDescending(n => n.SignalPercent).First())
            .OrderByDescending(n => n.SignalPercent)
            .ToList();
    }

    public static List<string> SavedProfiles()
    {
        var profiles = new List<string>();

        foreach (var raw in RunNetsh("wlan show profiles").Split('\n'))
        {
            // Hanya baris entri yang diindentasi ("    All User Profile : Nama").
            if (raw.Length == 0 || (raw[0] != ' ' && raw[0] != '\t'))
            {
                continue;
            }

            var separator = raw.IndexOf(':');

            if (separator < 0)
            {
                continue;
            }

            var name = Sanitize(raw[(separator + 1)..]);

            if (name.Length > 0 && !name.Equals("<None>", StringComparison.OrdinalIgnoreCase))
            {
                profiles.Add(name);
            }
        }

        return profiles.Distinct(StringComparer.OrdinalIgnoreCase).ToList();
    }

    public static string? CurrentSsid()
    {
        foreach (var raw in RunNetsh("wlan show interfaces").Split('\n'))
        {
            var line = raw.Trim();
            var separator = line.IndexOf(':');

            if (separator < 0)
            {
                continue;
            }

            if (line[..separator].Trim().Equals("SSID", StringComparison.OrdinalIgnoreCase))
            {
                var value = Sanitize(line[(separator + 1)..]);

                return value.Length > 0 ? value : null;
            }
        }

        return null;
    }

    /// <summary>Menyambung ke profil yang sudah tersimpan.</summary>
    public static bool ConnectProfile(string profileName, out string message)
    {
        RunNetsh($"wlan connect name=\"{Escape(profileName)}\"");

        if (WaitUntilConnected(profileName))
        {
            message = $"Terhubung ke {profileName}.";
            return true;
        }

        message = $"Belum berhasil terhubung ke {profileName}. Coba lagi sebentar lagi.";
        return false;
    }

    /// <summary>Membuat profil baru (terbuka atau WPA2-PSK) lalu menyambung.</summary>
    public static bool ConnectNew(string ssid, string? password, out string message)
    {
        var profilePath = Path.Combine(Path.GetTempPath(), "balilog-wifi-" + Guid.NewGuid().ToString("N") + ".xml");

        try
        {
            File.WriteAllText(profilePath, BuildProfileXml(ssid, password), new UTF8Encoding(false));

            RunNetsh($"wlan add profile filename=\"{profilePath}\" user=current");

            if (!SavedProfiles().Contains(ssid, StringComparer.OrdinalIgnoreCase))
            {
                message = $"Profil jaringan \"{ssid}\" tidak dapat dibuat. Periksa nama & password jaringan.";
                return false;
            }

            if (WaitUntilConnected(ssid))
            {
                message = $"Terhubung ke {ssid}.";
                return true;
            }

            // Kemungkinan password salah: buang profil buatan kita supaya
            // percobaan berikutnya memakai password yang baru.
            RunNetsh($"wlan delete profile name=\"{Escape(ssid)}\"");

            message = $"Gagal terhubung ke {ssid}. Periksa kembali password jaringan.";
            return false;
        }
        catch (Exception ex)
        {
            message = "Gagal menyiapkan profil Wi-Fi: " + ex.Message;
            return false;
        }
        finally
        {
            try
            {
                File.Delete(profilePath);
            }
            catch (Exception)
            {
                // berkas sementara - abaikan
            }
        }
    }

    private static bool WaitUntilConnected(string ssid)
    {
        for (var attempt = 0; attempt < 8; attempt++)
        {
            Thread.Sleep(1500);

            var current = CurrentSsid();

            if (current is not null && current.Equals(ssid, StringComparison.OrdinalIgnoreCase))
            {
                return true;
            }
        }

        return false;
    }

    private static string BuildProfileXml(string ssid, string? password)
    {
        var name = System.Security.SecurityElement.Escape(ssid) ?? ssid;

        var security = string.IsNullOrEmpty(password)
            ? "<authEncryption><authentication>open</authentication><encryption>none</encryption><useOneX>false</useOneX></authEncryption>"
            : "<authEncryption><authentication>WPA2PSK</authentication><encryption>AES</encryption><useOneX>false</useOneX></authEncryption>" +
              "<sharedKey><keyType>passPhrase</keyType><protected>false</protected><keyMaterial>" +
              (System.Security.SecurityElement.Escape(password) ?? string.Empty) +
              "</keyMaterial></sharedKey>";

        return "<?xml version=\"1.0\"?>" +
               "<WLANProfile xmlns=\"http://www.microsoft.com/networking/WLAN/profile/v1\">" +
               "<name>" + name + "</name>" +
               "<SSIDConfig><SSID><name>" + name + "</name></SSID></SSIDConfig>" +
               "<connectionType>ESS</connectionType><connectionMode>auto</connectionMode>" +
               "<MSM><security>" + security + "</security></MSM></WLANProfile>";
    }

    private static string Escape(string value) => value.Replace("\"", string.Empty);

    private static string RunNetsh(string arguments)
    {
        try
        {
            var startInfo = new ProcessStartInfo
            {
                FileName = "netsh.exe",
                Arguments = arguments,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true,
            };

            using var process = Process.Start(startInfo);

            if (process is null)
            {
                return string.Empty;
            }

            var output = process.StandardOutput.ReadToEnd();
            var error = process.StandardError.ReadToEnd();

            process.WaitForExit(TimeoutMs);

            return string.IsNullOrWhiteSpace(error) ? output : output + "\n" + error;
        }
        catch (Exception)
        {
            return string.Empty;
        }
    }

    private static string Sanitize(string value)
    {
        var builder = new StringBuilder(value.Length);

        foreach (var character in value)
        {
            if (!char.IsControl(character))
            {
                builder.Append(character);
            }
        }

        return builder.ToString().Trim();
    }
}
