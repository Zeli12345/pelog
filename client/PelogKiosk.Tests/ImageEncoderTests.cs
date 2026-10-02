using PelogKiosk.Core.Imaging;
using SkiaSharp;
using Xunit;

namespace PelogKiosk.Tests;

public class ImageEncoderTests
{
    private static byte[] CreateSourcePng(int width, int height)
    {
        using var bitmap = new SKBitmap(width, height);

        for (var y = 0; y < height; y++)
        {
            for (var x = 0; x < width; x += 4)
            {
                var color = new SKColor((byte)(x % 255), (byte)(y % 255), 128);
                bitmap.SetPixel(x, y, color);
            }
        }

        using var image = SKImage.FromBitmap(bitmap);
        using var data = image.Encode(SKEncodedImageFormat.Png, 90);

        return data.ToArray();
    }

    [Fact]
    public void Encode_Resizes_To_Max_Width_And_Uses_Webp()
    {
        var source = CreateSourcePng(1600, 900);

        var encoded = ImageEncoder.Encode(source, "webp_fallback_jpeg", 1280, 70, 60);

        Assert.NotNull(encoded);
        Assert.Equal("webp", encoded!.Format);

        using var decoded = SKBitmap.Decode(encoded.Bytes);

        Assert.NotNull(decoded);
        Assert.Equal(1280, decoded!.Width);
        Assert.Equal(720, decoded.Height);
    }

    [Fact]
    public void Encode_Keeps_Small_Image_Size()
    {
        var source = CreateSourcePng(800, 600);

        var encoded = ImageEncoder.Encode(source, "webp_fallback_jpeg", 1280, 70, 60);

        Assert.NotNull(encoded);

        using var decoded = SKBitmap.Decode(encoded!.Bytes);

        Assert.Equal(800, decoded!.Width);
    }

    [Fact]
    public void Encode_Uses_Jpeg_When_Requested()
    {
        var source = CreateSourcePng(640, 480);

        var encoded = ImageEncoder.Encode(source, "jpeg_only", 1280, 70, 60);

        Assert.NotNull(encoded);
        Assert.Equal("jpeg", encoded!.Format);

        using var decoded = SKBitmap.Decode(encoded.Bytes);
        Assert.Equal(640, decoded!.Width);
    }
}
