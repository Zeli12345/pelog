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
                .Select(part => int.TryParse(part, out var value) ? value : 0)
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

    public string? GetStagedVersion() => _store.GetKv("update_staged_version");

    public async Task<UpdateCheckResult> CheckAndStageAsync(int intervalHours, CancellationToken cancellationToken = default)
    {
        var lastCheck = _store.GetKv("update_last_check");

        if (DateTimeOffset.TryParse(lastCheck, out var lastAt) &&
            _now() - lastAt < TimeSpan.FromHours(Math.Max(1, intervalHours)))
        {
            return new UpdateCheckResult(UpdateCheckOutcome.Skipped);
        }

        var result = await _api.GetLatestAppAsync(cancellationToken);
        _store.SetKv("update_last_check", _now().ToString("o"));

        if (!result.Ok || result.Data is null)
        {
            return new UpdateCheckResult(UpdateCheckOutcome.Failed, null, result.ErrorMessage);
        }

        var release = result.Data;

        if (!release.Available || string.IsNullOrWhiteSpace(release.Version) ||
            !IsNewer(release.Version, AppInfo.Version))
        {
            return new UpdateCheckResult(UpdateCheckOutcome.UpToDate, release.Version);
        }

        if (_store.GetKv("update_staged_version") == release.Version)
        {
            return new UpdateCheckResult(UpdateCheckOutcome.Staged, release.Version);
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

    private static void TryDelete(string path)
    {
        try
        {
            File.Delete(path);
        }
        catch (IOException)
        {
            // abaikan
        }
    }
}
