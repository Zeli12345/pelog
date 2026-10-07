namespace PelogKiosk.Core.Services;

/// <summary>
/// Kebijakan kapan kiosk boleh menangkap screenshot baru. Mencegah capture
/// manual (permintaan dashboard) dan capture terjadwal bertabrakan dalam
/// hitungan detik — penyebab baris screenshot "dobel" berisi gambar identik.
/// </summary>
public static class ScreenshotPolicy
{
    /// <summary>Jarak minimum antar-capture.</summary>
    public static readonly TimeSpan MinSpacing = TimeSpan.FromSeconds(90);

    public static bool ShouldCapture(DateTimeOffset? lastCapturedAt, bool hasPendingCapture, DateTimeOffset now)
    {
        // Capture yang belum terkirim harus dikirim dulu — jangan menimpa
        // atau menambah capture baru.
        if (hasPendingCapture)
        {
            return false;
        }

        if (lastCapturedAt is null)
        {
            return true;
        }

        // Selisih negatif (jam perangkat mundur/meleset) juga dianggap
        // belum cukup jarak — lebih aman tidak menambah capture.
        return now - lastCapturedAt.Value >= MinSpacing;
    }
}
