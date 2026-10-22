using System.Text.Json;
using PelogKiosk.Core.Api;
using PelogKiosk.Core.Models;
using PelogKiosk.Core.Services;

namespace PelogKiosk.Core.Services;

public sealed record UpdateGateDecision(
    bool Block,
    string Mode,
    string? TargetVersion,
    string? State,
    string? Message,
    int Attempts = 0);

/// <summary>
/// Membaca status agen update (SYSTEM) dan memutuskan apakah kiosk boleh
/// menampilkan layar kunci. Bila update sudah diunduh/diverifikasi, kiosk
/// TIDAK boleh login sebelum versi terpasang.
/// </summary>
public static class UpdateGate
{
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web)
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        PropertyNameCaseInsensitive = true,
    };

    public static string StatePath(string dataDirectory)
    {
        var configDirectory = Path.GetDirectoryName(dataDirectory) ?? dataDirectory;

        return Path.Combine(configDirectory, "updates", "state.json");
    }

    public static UpdateStateInfo? ReadState(string dataDirectory)
    {
        try
        {
            var path = StatePath(dataDirectory);

            if (!File.Exists(path))
            {
                return null;
            }

            return JsonSerializer.Deserialize<UpdateStateInfo>(File.ReadAllText(path), JsonOptions);
        }
        catch (Exception)
        {
            return null;
        }
    }

    public static async Task<UpdateGateDecision> EvaluateAsync(
        PelogApiClient api,
        string dataDirectory,
        CancellationToken cancellationToken = default)
    {
        var state = ReadState(dataDirectory);
        var target = state?.TargetVersion;
        var stateName = state?.State ?? "idle";
        var attempts = state?.Attempts ?? 0;

        if (state is not null && !string.IsNullOrWhiteSpace(target))
        {
            var newer = UpdateService.IsNewer(target, AppInfo.Version);

            if (newer && stateName == "failed_permanent")
            {
                return new UpdateGateDecision(
                    true,
                    "failed",
                    target,
                    stateName,
                    state?.LastError ?? "Pembaruan gagal dipasang setelah beberapa percobaan.",
                    attempts);
            }

            if (newer && stateName is "checking" or "downloading" or "staged" or "deferred" or "installing" or "installed_pending_reboot" or "failed_retry")
            {
                var message = stateName switch
                {
                    "checking" => "Memeriksa pembaruan dari server…",
                    "downloading" => "Mengunduh installer pembaruan. Jangan matikan komputer.",
                    "deferred" => "Ada sesi yang masih aktif. Restart komputer untuk menyelesaikan pembaruan.",
                    "installing" => "Installer sedang berjalan. Jangan matikan komputer.",
                    "failed_retry" => "Percobaan pembaruan gagal. Akan dicoba lagi saat boot berikutnya.",
                    _ => "Pembaruan sudah diunduh dan menunggu dipasang.",
                };

                return new UpdateGateDecision(true, "staged", target, stateName, message, attempts);
            }
        }

        // Cek server publik: berlaku bahkan sebelum perangkat terdaftar.
        var versionResult = await api.GetPublicVersionAsync(cancellationToken);

        if (versionResult.Ok && versionResult.Data is { } version)
        {
            var latest = version.LatestVersion ?? version.AppVersion;
            var minimum = version.MinSupportedVersion;

            if (!string.IsNullOrWhiteSpace(minimum) && UpdateService.IsNewer(minimum, AppInfo.Version))
            {
                return new UpdateGateDecision(
                    true,
                    "required",
                    latest,
                    "waiting",
                    $"Versi minimum yang didukung adalah {minimum}. Pembaruan wajib dipasang sebelum memakai laptop.",
                    attempts);
            }

            if (version.Mandatory && !string.IsNullOrWhiteSpace(latest) && UpdateService.IsNewer(latest, AppInfo.Version))
            {
                return new UpdateGateDecision(
                    true,
                    "required",
                    latest,
                    "waiting",
                    "Pembaruan wajib tersedia. Menunggu installer dari server.",
                    attempts);
            }
        }

        return new UpdateGateDecision(false, "allow", target, stateName, null, attempts);
    }
}
