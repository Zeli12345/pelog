namespace PelogKiosk.App.Services;

/// <summary>
/// Log lokal ringan ke berkas di direktori data (kiosk.log).
/// Dipakai untuk kejadian penting yang tidak fatal, mis. hook keyboard
/// atau hotkey sesi yang gagal dipasang.
/// </summary>
internal static class LocalLog
{
    public static void Write(string dataDirectory, string message)
    {
        try
        {
            File.AppendAllText(
                Path.Combine(dataDirectory, "kiosk.log"),
                $"{DateTime.Now:O} {message}{Environment.NewLine}");
        }
        catch (Exception)
        {
            // Logging opsional â€” jangan pernah mengganggu jalannya kiosk.
        }
    }
}
