using SkiaSharp;

namespace PelogKiosk.Core.Imaging;

public sealed record EncodedImage(byte[] Bytes, string Format);

/// <summary>
/// Encoder gambar: WebP (utama) dengan fallback JPEG, plus pengecilan ukuran.
/// Dipakai untuk screenshot sesi (maks 1280 px, target ≤120 KB).
/// </summary>
public static class ImageEncoder
{
    public static EncodedImage? Encode(
        byte[] sourceBytes,
        string preferredFormat,
        int maxWidth,
        int webpQuality,
        int jpegQuality)
    {
        using var original = SKBitmap.Decode(sourceBytes);

        if (original is null)
        {
            return null;
        }

        SKBitmap? resized = null;

        try
        {
            var target = original;

            if (maxWidth > 0 && original.Width > maxWidth)
            {
                var ratio = (double)maxWidth / original.Width;
                var width = maxWidth;
                var height = Math.Max(1, (int)Math.Round(original.Height * ratio));

                resized = original.Resize(
                    new SKImageInfo(width, height),
                    new SKSamplingOptions(SKFilterMode.Linear, SKMipmapMode.Linear));

                if (resized is not null)
                {
                    target = resized;
                }
            }

            using var image = SKImage.FromBitmap(target);

            if (!preferredFormat.Equals("jpeg_only", StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    using var webp = image.Encode(SKEncodedImageFormat.Webp, Math.Clamp(webpQuality, 1, 100));

                    if (webp is not null && webp.Size > 0)
                    {
                        return new EncodedImage(webp.ToArray(), "webp");
                    }
                }
                catch (Exception)
                {
                    // lanjut ke fallback JPEG
                }
            }

            using var jpeg = image.Encode(SKEncodedImageFormat.Jpeg, Math.Clamp(jpegQuality, 1, 100));

            if (jpeg is null || jpeg.Size == 0)
            {
                return null;
            }

            return new EncodedImage(jpeg.ToArray(), "jpeg");
        }
        finally
        {
            resized?.Dispose();
        }
    }
}
