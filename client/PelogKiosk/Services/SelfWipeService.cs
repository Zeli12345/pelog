using System.Diagnostics;
using PelogKiosk.Core.Api;

namespace PelogKiosk.App.Services;

/// <summary>
/// Wipe total saat perangkat DIHAPUS dari dashboard (HTTP 410 device_revoked).
/// Menjalankan Scheduled Task elevated "BALILogSelfWipe" yang dibuat installer
/// (seperti task BALILogHardening*) supaya uninstaller berjalan tanpa UAC,
/// lalu keluar agar task dapat membersihkan sisa instalasi.
/// Semua langkah best effort â€” jalur ini tidak pernah melempar exception.
/// </summary>
internal static class SelfWipeService
{
    public const string TaskName = "BALILogSelfWipe";

    private const string LogPath = @"C:\Users\Public\pelog-wipe.log";

    private const int TaskWaitMilliseconds = 15_000;

    private const int ExitDelayMilliseconds = 3_000;

    private static int _triggered;

    /// <summary>
    /// True hanya bila server benar-benar menyatakan perangkat dihapus
    /// (HTTP 410 + kode "device_revoked"). Timeout/network/401 selalu false.
    /// </summary>
    public static bool IsRevoked<T>(ApiResult<T> result) =>
        DeviceRevocation.IsRevoked(result.HttpStatus, result.ErrorCode);

    /// <summary>
    /// Jalankan wipe total: catat alasan, picu task elevated, lalu hentikan
    /// proses kiosk. Hanya boleh dipanggil bila pemanggil sudah memastikan
    /// Config.SelfWipeOnRevoke aktif (dan idealnya dari UI thread).
    /// </summary>
    public static void Trigger(string reason)
    {
        if (Interlocked.Exchange(ref _triggered, 1) == 1)
        {
            return;
        }

        try
        {
            WriteLog($"REVOKED: {reason}");

            if (!RunSelfWipeTask())
            {
                WriteLog("task BALILogSelfWipe GAGAL dijalankan â€” wipe total tidak berjalan, perlu perhatian Admin IT.");
            }

            // Beri kesempatan task elevated menutup/membersihkan aplikasi ini
            // sebelum proses kiosk keluar sendiri.
            Thread.Sleep(ExitDelayMilliseconds);

            Environment.Exit(0);
        }
        catch (Exception)
        {
            // Best effort â€” apa pun yang terjadi, jangan melempar keluar.
        }
    }

    private static bool RunSelfWipeTask()
    {
        try
        {
            var startInfo = new ProcessStartInfo
            {
                FileName = "schtasks.exe",
                Arguments = $"/Run /TN \"{TaskName}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
            };

            using var process = Process.Start(startInfo);

            if (process is null)
            {
                return false;
            }

            process.WaitForExit(TaskWaitMilliseconds);

            WriteLog($"schtasks /Run /TN \"{TaskName}\" exit={process.ExitCode}");

            return process.ExitCode == 0;
        }
        catch (Exception ex)
        {
            WriteLog($"schtasks /Run /TN \"{TaskName}\" gagal: {ex.GetType().Name}: {ex.Message}");

            return false;
        }
    }

    private static void WriteLog(string message)
    {
        try
        {
            File.AppendAllText(LogPath, $"{DateTime.Now:O} {message}{Environment.NewLine}");
        }
        catch (Exception)
        {
            // Aplikasi mungkin tidak punya hak tulis â€” logging opsional.
        }
    }
}
