using BalilogKiosk.Core.Api;
using BalilogKiosk.Core.Imaging;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Time;
using SkiaSharp;
using Xunit;

namespace BalilogKiosk.Tests;

/// <summary>
/// Test integrasi end-to-end: klien C# berbicara dengan server staging Laravel yang berjalan.
///
/// Cara menjalankan:
///   1. Jalankan server staging (php artisan serve) + MariaDB.
///   2. Set env BALILOG_E2E_CODE dari `php artisan balilog:enrollment-code`.
///   3. dotnet test
///
/// Jika server tidak berjalan, test akan dilewati secara otomatis.
/// </summary>
[Trait("Category", "Integration")]
public class ApiIntegrationTests
{
    private static string BaseUrl =>
        Environment.GetEnvironmentVariable("BALILOG_E2E_URL") ?? "http://127.0.0.1:8000";

    [Fact]
    public async Task Full_Client_Flow_Against_Staging_Server()
    {
        var enrollmentCode = Environment.GetEnvironmentVariable("BALILOG_E2E_CODE");

        using var http = new HttpClient();
        var clock = new ServerClock();
        var api = new BalilogApiClient(http, clock, BaseUrl + "/api/v1");

        if (!await api.HealthAsync())
        {
            // Server staging tidak berjalan — lewati (mode unit test saja).
            return;
        }

        if (string.IsNullOrWhiteSpace(enrollmentCode))
        {
            // Tidak ada kode enrollment -> mode unit test saja (bukan kegagalan).
            return;
        }

        // 1) Enrollment perangkat baru
        var enroll = await api.EnrollAsync(new EnrollRequest
        {
            EnrollmentCode = enrollmentCode!,
            DeviceUuid = Guid.NewGuid().ToString(),
            Hostname = "E2E-" + Guid.NewGuid().ToString("N")[..8],
            DeviceType = "laptop",
            AgentVersion = "e2e-1.0.0",
            WindowsVersion = "Windows 10 (test)",
            StorageTotalGb = 256,
            StorageUsedGb = 100,
        });

        Assert.True(enroll.Ok, enroll.ErrorMessage);
        Assert.False(string.IsNullOrWhiteSpace(enroll.Data!.DeviceToken));

        api.DeviceToken = enroll.Data.DeviceToken;
        Assert.True(clock.IsSynced, "Jam server harus tersinkron dari respons API.");

        // 2) Bootstrap: cache siswa/guru/mapel
        var bootstrap = await api.BootstrapAsync();

        Assert.True(bootstrap.Ok, bootstrap.ErrorMessage);
        Assert.NotEmpty(bootstrap.Data!.Students);
        Assert.NotEmpty(bootstrap.Data.Subjects);

        // 3) Siklus sesi: start -> heartbeat -> screenshot -> end
        var student = bootstrap.Data.Students[0];
        var sessionUuid = Guid.NewGuid().ToString();

        var start = await api.StartSessionAsync(new StartSessionRequest
        {
            SessionUuid = sessionUuid,
            UserType = "student",
            Nisn = student.Nisn,
            SubjectId = bootstrap.Data.Subjects.FirstOrDefault()?.Id,
            UsagePurpose = "Uji integrasi otomatis dari klien C#",
            StartedAtClient = DateTimeOffset.UtcNow,
            StorageTotalGb = 256,
            StorageUsedGb = 100,
        });

        Assert.True(start.Ok, start.ErrorMessage);
        Assert.True(start.Data!.Active);

        var heartbeat = await api.HeartbeatAsync(sessionUuid);
        Assert.True(heartbeat.Ok, heartbeat.ErrorMessage);

        // Screenshot: encode WebP via ImageEncoder lalu unggah multipart.
        var png = CreateTestPng(1024, 640);
        var encoded = ImageEncoder.Encode(png, "webp_fallback_jpeg", 1280, 70, 60);

        Assert.NotNull(encoded);

        var extension = encoded!.Format == "webp" ? "webp" : "jpg";
        var screenshotPath = Path.Combine(Path.GetTempPath(), $"balilog-e2e-{sessionUuid}.{extension}");
        await File.WriteAllBytesAsync(screenshotPath, encoded.Bytes);

        var upload = await api.UploadScreenshotAsync(
            sessionUuid,
            screenshotPath,
            Guid.NewGuid().ToString(),
            DateTimeOffset.UtcNow);

        Assert.True(upload.Ok, upload.ErrorMessage);
        Assert.Equal(encoded.Format, upload.Data!.Format);
        Assert.True(upload.Data.SizeBytes > 0);

        var end = await api.EndSessionAsync(new EndSessionRequest
        {
            SessionUuid = sessionUuid,
            CloseReason = "normal",
            EndedAtClient = DateTimeOffset.UtcNow,
        });

        Assert.True(end.Ok, end.ErrorMessage);
        Assert.False(end.Data!.Active);

        // 4) Sinkronisasi batch sesi offline (jalur recovery)
        var offlineUuid = Guid.NewGuid().ToString();
        var sync = await api.SyncSessionsAsync([
            new SyncSessionItem
            {
                SessionUuid = offlineUuid,
                UserType = "student",
                Nisn = student.Nisn,
                UsagePurpose = "Sesi offline uji integrasi",
                StartedAtClient = DateTimeOffset.UtcNow.AddMinutes(-25),
                EndedAtClient = DateTimeOffset.UtcNow.AddMinutes(-12),
                CloseReason = "recovery",
            },
        ]);

        Assert.True(sync.Ok, sync.ErrorMessage);
        Assert.Equal("created", sync.Data!.Results[0].Status);

        // Idempotensi: kirim ulang harus "skipped".
        var syncAgain = await api.SyncSessionsAsync([
            new SyncSessionItem
            {
                SessionUuid = offlineUuid,
                UserType = "student",
                Nisn = student.Nisn,
                UsagePurpose = "Sesi offline uji integrasi",
                StartedAtClient = DateTimeOffset.UtcNow.AddMinutes(-25),
                EndedAtClient = DateTimeOffset.UtcNow.AddMinutes(-12),
                CloseReason = "recovery",
            },
        ]);

        Assert.True(syncAgain.Ok);
        Assert.Equal("skipped", syncAgain.Data!.Results[0].Status);

        // 5) Token salah harus ditolak
        var wrongTokenApi = new BalilogApiClient(new HttpClient(), new ServerClock(), BaseUrl + "/api/v1")
        {
            DeviceToken = "token-palsu",
        };

        var unauthorized = await wrongTokenApi.BootstrapAsync();

        Assert.False(unauthorized.Ok);
        Assert.Equal(401, unauthorized.HttpStatus);

        File.Delete(screenshotPath);
    }

    private static byte[] CreateTestPng(int width, int height)
    {
        using var bitmap = new SKBitmap(width, height);

        for (var y = 0; y < height; y += 2)
        {
            for (var x = 0; x < width; x += 2)
            {
                bitmap.SetPixel(x, y, new SKColor((byte)(x % 255), (byte)(y % 255), 120));
            }
        }

        using var image = SKImage.FromBitmap(bitmap);
        using var data = image.Encode(SKEncodedImageFormat.Png, 90);

        return data.ToArray();
    }
}
