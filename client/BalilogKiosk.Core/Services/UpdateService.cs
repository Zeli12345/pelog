using System.Globalization;
using System.Security.Cryptography;
using System.Text.Json;
using BalilogKiosk.Core.Api;
using BalilogKiosk.Core.Data;

namespace BalilogKiosk.Core.Services;

public enum UpdateCheckOutcome
{
    Skipped,
    UpToDate,
    Staged,
    Failed,
}

public sealed record UpdateCheckResult(UpdateCheckOutcome Outcome, string? Version = null, string? Message = null);

/// <summary>
/// Auto-update client:
/// memeriksa rilis terbaru di server, mengunduh installer, memverifikasi SHA-256,
/// lalu menaruh manifest agar Scheduled Task (SYSTEM) menerapkannya tanpa UAC.
/// </summary>
public sealed class UpdateService
{
    private readonly LocalStore _store;

    private readonly BalilogApiClient _api;

    private readonly string _configDirectory;

    private readonly string _dataDirectory;

    private readonly Func<DateTimeOffset> _now;

    public UpdateService(
        LocalStore store,
        BalilogApiClient api,
        string dataDirectory,
        Func<DateTimeOffset>? nowProvider = null)
    {
        _store = store;
        _api = api;
        _dataDirectory = dataDirectory;
        _configDirectory = Path.GetDirectoryName(dataDirectory) ?? dataDirectory;
        _now = nowProvider ?? (() => DateTimeOffset.UtcNow);
    }

    /// <summary>Perbandingan versi sederhana (mis. 1.10.0 &gt; 1.9.9).</summary>
    public static bool IsNewer(string? candidate, string current)
    {
        if (string.IsNullOrWhiteSpace(candidate))
        {
            return false;
        }

        static int[] Parse(string version) =>
            version.Trim().TrimStart('v', 'V').Split('-', '+')[0]
                .Split('.')
                .Select(part => int.TryParse(part, NumberStyles.Integer, CultureInfo.InvariantCulture, out var value) ? value : 0)
                .ToArray();

        var left = Parse(candidate);
        var right = Parse(current);

        for (var i = 0; i < Math.Max(left.Length, right.Length); i++)
        {
            var a = i < left.Length ? left[i] : 0;
            var b = i < right.Length ? right[i] : 0;

            if (a != b)
            {
                return a > b;
            }
        }

        return false;
    }

    public static string ComputeSha256(string path)
    {
        using var stream = File.OpenRead(path);
        using var sha = SHA256.Create();

        return Convert.ToHexString(sha.ComputeHash(stream)).ToLowerInvariant();
    }

    /// <summary>
    /// Membersihkan sisa staging yang sudah tidak relevan - mis. setelah update
    /// diterapkan oleh task SYSTEM saat boot: manifest + installer dihapus agar
    /// tidak menumpuk. Aman dipanggil kapan saja.
    /// </summary>
    public void CleanupStaleStaging()
    {
        var staged = _store.GetKv("update_staged_version");

        if (string.IsNullOrEmpty(staged) || IsNewer(staged, AppInfo.Version))
        {
            return;
        }

        ClearStagedState();
    }

    /// <summary>Menghapus manifest, installer, dan penanda staging yang tidak valid.</summary>
    private void ClearStagedState()
    {
        var manifestPath = Path.Combine(_configDirectory, "update.json");

        try
        {
            if (File.Exists(manifestPath))
            {
                using var document = JsonDocument.Parse(File.ReadAllText(manifestPath));

                if (document.RootElement.TryGetProperty("installer_path", out var installerPath))
                {
                    TryDelete(installerPath.GetString() ?? string.Empty);
                }
            }
        }
        catch (Exception)
        {
            // Manifest rusak / tidak terbaca - berkas manifest tetap dihapus di bawah.
        }

        TryDelete(manifestPath);
        _store.SetKv("update_staged_version", "");
    }

    public async Task<UpdateCheckResult> CheckAndStageAsync(int intervalHours, CancellationToken cancellationToken = default)
    {
        CleanupStaleStaging();

        var lastCheck = _store.GetKv("update_last_check");

        if (DateTimeOffset.TryParse(lastCheck, CultureInfo.InvariantCulture, DateTimeStyles.RoundtripKind, out var lastAt) &&
            _now() - lastAt < TimeSpan.FromHours(Math.Max(1, intervalHours)))
        {
            return new UpdateCheckResult(UpdateCheckOutcome.Skipped);
        }

        var result = await _api.GetLatestAppAsync(cancellationToken);

        if (!result.Ok || result.Data is null)
        {
            // Jangan simpan waktu cek saat gagal: tick berikutnya harus mencoba lagi.
            return new UpdateCheckResult(UpdateCheckOutcome.Failed, null, result.ErrorMessage);
        }

        _store.SetKv("update_last_check", _now().ToString("o"));

        var release = result.Data;

        if (!release.Available || string.IsNullOrWhiteSpace(release.Version) ||
            !IsNewer(release.Version, AppInfo.Version))
        {
            return new UpdateCheckResult(UpdateCheckOutcome.UpToDate, release.Version);
        }

        if (_store.GetKv("update_staged_version") == release.Version)
        {
            if (IsStagingIntact(release.Version))
            {
                return new UpdateCheckResult(UpdateCheckOutcome.Staged, release.Version);
            }

            // Staging lama sudah tidak lengkap (mis. installer sudah diterapkan):
            // bersihkan agar versi ini diunduh ulang, bukan macet selamanya.
            ClearStagedState();
        }

        var updatesDirectory = Path.Combine(_dataDirectory, "updates");
        Directory.CreateDirectory(updatesDirectory);

        var installerPath = Path.Combine(updatesDirectory, $"BALI-LOG_Setup_{release.Version}.exe");

        if (!await _api.DownloadInstallerAsync(release.Url ?? "app/installer", installerPath, cancellationToken))
        {
            return new UpdateCheckResult(UpdateCheckOutcome.Failed, release.Version, "Unduhan installer gagal.");
        }

        var hash = ComputeSha256(installerPath);

        if (!string.IsNullOrEmpty(release.Sha256) &&
            !hash.Equals(release.Sha256, StringComparison.OrdinalIgnoreCase))
        {
            TryDelete(installerPath);

            return new UpdateCheckResult(UpdateCheckOutcome.Failed, release.Version, "Hash installer tidak cocok.");
        }

        // Manifest dibaca oleh Scheduled Task (SYSTEM) untuk menerapkan update.
        var manifest = JsonSerializer.Serialize(new
        {
            version = release.Version,
            installer_path = installerPath,
            sha256 = hash,
            mandatory = release.Mandatory,
            staged_at = _now().ToString("o"),
        });

        File.WriteAllText(Path.Combine(_configDirectory, "update.json"), manifest);
        _store.SetKv("update_staged_version", release.Version);

        return new UpdateCheckResult(UpdateCheckOutcome.Staged, release.Version);
    }

    /// <summary>
    /// Memastikan staging untuk <paramref name="version"/> masih benar-benar ada:
    /// manifest cocok, installer ada, dan hash-nya sesuai.
    /// </summary>
    private bool IsStagingIntact(string version)
    {
        var manifestPath = Path.Combine(_configDirectory, "update.json");

        if (!File.Exists(manifestPath))
        {
            return false;
        }

        try
        {
            using var document = JsonDocument.Parse(File.ReadAllText(manifestPath));
            var root = document.RootElement;

            if (!root.TryGetProperty("version", out var manifestVersion) ||
                !string.Equals(manifestVersion.GetString(), version, StringComparison.Ordinal))
            {
                return false;
            }

            if (!root.TryGetProperty("installer_path", out var installerPathElement))
            {
                return false;
            }

            var installerPath = installerPathElement.GetString();

            if (string.IsNullOrWhiteSpace(installerPath) || !File.Exists(installerPath))
            {
                return false;
            }

            if (!root.TryGetProperty("sha256", out var sha256Element))
            {
                return false;
            }

            var sha256 = sha256Element.GetString();

            return !string.IsNullOrWhiteSpace(sha256) &&
                   ComputeSha256(installerPath).Equals(sha256, StringComparison.OrdinalIgnoreCase);
        }
        catch (Exception)
        {
            // Manifest rusak / installer tidak terbaca -> staging dianggap tidak valid.
            return false;
        }
    }

    private static void TryDelete(string path)
    {
        if (string.IsNullOrWhiteSpace(path))
        {
            return;
        }

        try
        {
            File.Delete(path);
        }
        catch (Exception)
        {
            // abaikan - pembersihan bersifat opsional
        }
    }
}
