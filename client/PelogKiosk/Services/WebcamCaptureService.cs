using PelogKiosk.Core.Imaging;
using Windows.Devices.Enumeration;
using Windows.Media.Capture;
using Windows.Media.MediaProperties;
using Windows.Storage.Streams;

namespace PelogKiosk.App.Services;

/// <summary>
/// Capture foto dari webcam. Memakai Windows MediaCapture dengan mode
/// SharedReadOnly agar tetap bisa mengambil gambar saat aplikasi lain
/// (mis. video call) sedang memakai kamera — bila driver mendukung.
/// </summary>
public static class WebcamCaptureService
{
    public static async Task<string?> CaptureAsync(
        string directory,
        string baseName,
        int maxWidth,
        int quality)
    {
        MediaCapture? capture = null;

        try
        {
            var devices = await DeviceInformation.FindAllAsync(DeviceClass.VideoCapture);
            var device = devices.FirstOrDefault(item => item.IsEnabled);

            if (device is null)
            {
                return null;
            }

            MediaCaptureSharingMode[] modes = [MediaCaptureSharingMode.SharedReadOnly, MediaCaptureSharingMode.ExclusiveControl];

            foreach (var sharingMode in modes)
            {
                foreach (var streamingMode in new[] { StreamingCaptureMode.Video })
                {
                    try
                    {
                        capture = new MediaCapture();

                        var settings = new MediaCaptureInitializationSettings
                        {
                            VideoDeviceId = device.Id,
                            StreamingCaptureMode = streamingMode,
                            SharingMode = sharingMode,
                        };

                        await capture.InitializeAsync(settings);
                        break;
                    }
                    catch (Exception)
                    {
                        capture?.Dispose();
                        capture = null;
                    }
                }

                if (capture is not null)
                {
                    break;
                }
            }

            if (capture is null)
            {
                return null;
            }

            using var stream = new InMemoryRandomAccessStream();
            await capture.CapturePhotoToStreamAsync(ImageEncodingProperties.CreateJpeg(), stream);
            stream.Seek(0);

            var length = checked((int)stream.Size);
            var bytes = new byte[length];
            using var reader = new DataReader(stream.GetInputStreamAt(0));
            await reader.LoadAsync((uint)length);
            reader.ReadBytes(bytes);

            var encoded = ImageEncoder.Encode(bytes, "webp_fallback_jpeg", maxWidth, quality, quality);

            if (encoded is null)
            {
                return null;
            }

            Directory.CreateDirectory(directory);

            var extension = encoded.Format == "webp" ? "webp" : "jpg";
            var path = Path.Combine(directory, $"{baseName}.{extension}");
            await File.WriteAllBytesAsync(path, encoded.Bytes);

            return path;
        }
        catch (Exception)
        {
            // Kamera sibuk / tidak ada / driver tidak mendukung shared mode:
            // lewati capture ini, coba lagi pada interval berikutnya.
            return null;
        }
        finally
        {
            if (capture is not null)
            {
                try
                {
                    capture.Dispose();
                }
                catch (Exception)
                {
                    // abaikan
                }
            }
        }
    }
}
