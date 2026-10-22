using PelogKiosk.Core.Services;

namespace PelogKiosk.Tests;

public sealed class WebcamCapturePolicyTests
{
    [Fact]
    public void Capture_pertama_langsung()
    {
        Assert.True(WebcamCapturePolicy.ShouldCapture(null, false, DateTimeOffset.UtcNow, TimeSpan.FromMinutes(15)));
    }

    [Fact]
    public void Tidak_capture_saat_masih_pending()
    {
        Assert.False(WebcamCapturePolicy.ShouldCapture(null, true, DateTimeOffset.UtcNow, TimeSpan.FromMinutes(15)));
    }

    [Fact]
    public void Menunggu_interval_berikutnya()
    {
        var now = DateTimeOffset.UtcNow;

        Assert.False(WebcamCapturePolicy.ShouldCapture(now.AddMinutes(-5), false, now, TimeSpan.FromMinutes(15)));
        Assert.True(WebcamCapturePolicy.ShouldCapture(now.AddMinutes(-15), false, now, TimeSpan.FromMinutes(15)));
    }
}
