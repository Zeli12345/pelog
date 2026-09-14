using System.Drawing.Imaging;
using BalilogKiosk.Core.Imaging;

namespace BalilogKiosk.App.Services;

/// <summary>
/// Menangkap layar utama lalu meng-encode ke WebP (fallback JPEG) sesuai konfigurasi server.
/// </summary>
public static class ScreenCapture
{
    public static string? CaptureToFile(string outputDirectory, string sessionUuid, string preferredFormat, int maxWidth, int webpQuality, int jpegQuality)
    {
        try
        {
            Directory.CreateDirectory(outputDirectory);

            var bounds = Screen.PrimaryScreen?.Bounds ?? new Rectangle(0, 0, 1280, 720);

            using var bitmap = new Bitmap(bounds.Width, bounds.Height);

            using (var graphics = Graphics.FromImage(bitmap))
            {
                graphics.CopyFromScreen(bounds.Location, Point.Empty, bounds.Size);
            }

            byte[] sourceBytes;

            using (var memory = new MemoryStream())
            {
                bitmap.Save(memory, ImageFormat.Png);
                sourceBytes = memory.ToArray();
            }

            var encoded = ImageEncoder.Encode(sourceBytes, preferredFormat, maxWidth, webpQuality, jpegQuality);

            if (encoded is null)
            {
                return null;
            }

            var extension = encoded.Format == "webp" ? "webp" : "jpg";
            var path = Path.Combine(outputDirectory, $"{sessionUuid}.{extension}");

            File.WriteAllBytes(path, encoded.Bytes);

            return path;
        }
        catch (Exception)
        {
            return null;
        }
    }
}
