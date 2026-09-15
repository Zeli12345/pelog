using System.Text.Json;

namespace BalilogKiosk.Core.Config;

public sealed class KioskConfig
{
    public string BaseUrl { get; set; } = "http://127.0.0.1:8000";

    public string EnrollmentCode { get; set; } = string.Empty;

    /// <summary>Mode uji: mempercepat timer screenshot (dipakai di VM/QA saja).</summary>
    public bool TestMode { get; set; }

    /// <summary>Mengganti menit screenshot saat TestMode aktif.</summary>
    public int TestScreenshotMinute { get; set; } = 1;

    /// <summary>Hardening HKCU kiosk — hanya diaktifkan pada mesin produksi (oleh installer).</summary>
    public bool HardeningEnabled { get; set; }

    /// <summary>Hash password admin kiosk (format: pbkdf2-sha256$iterations$salt$hash).</summary>
    public string? AdminPasswordHash { get; set; }

    /// <summary>Interval pemeriksaan auto-update (jam).</summary>
    public int UpdateCheckHours { get; set; } = 6;

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        WriteIndented = true,
        PropertyNameCaseInsensitive = true,
    };

    public static KioskConfig Load(string path)
    {
        try
        {
            if (File.Exists(path))
            {
                var json = File.ReadAllText(path);
                var config = JsonSerializer.Deserialize<KioskConfig>(json, JsonOptions);

                if (config is not null)
                {
                    return config;
                }
            }
        }
        catch (Exception)
        {
            // konfigurasi rusak -> pakai default
        }

        return new KioskConfig();
    }

    public void Save(string path)
    {
        var directory = Path.GetDirectoryName(path);

        if (!string.IsNullOrEmpty(directory))
        {
            Directory.CreateDirectory(directory);
        }

        File.WriteAllText(path, JsonSerializer.Serialize(this, JsonOptions));
    }

    public string NormalizedBaseUrl()
    {
        var url = BaseUrl.Trim().TrimEnd('/');

        return url.EndsWith("/api/v1", StringComparison.OrdinalIgnoreCase)
            ? url
            : url + "/api/v1";
    }
}
