using PelogKiosk.Core.Services;
using Xunit;

namespace PelogKiosk.Tests;

public class ScreenshotPolicyTests
{
    private static readonly DateTimeOffset Now = new(2026, 10, 7, 12, 0, 0, TimeSpan.FromHours(8));

    [Fact]
    public void ShouldCapture_true_when_no_history_and_nothing_pending()
    {
        Assert.True(ScreenshotPolicy.ShouldCapture(null, hasPendingCapture: false, Now));
    }

    [Fact]
    public void ShouldCapture_false_when_capture_is_pending()
    {
        Assert.False(ScreenshotPolicy.ShouldCapture(null, hasPendingCapture: true, Now));
    }

    [Fact]
    public void ShouldCapture_false_within_min_spacing()
    {
        Assert.False(ScreenshotPolicy.ShouldCapture(Now.AddSeconds(-30), hasPendingCapture: false, Now));
    }

    [Fact]
    public void ShouldCapture_true_after_min_spacing()
    {
        var last = Now - ScreenshotPolicy.MinSpacing - TimeSpan.FromSeconds(1);

        Assert.True(ScreenshotPolicy.ShouldCapture(last, hasPendingCapture: false, Now));
    }

    [Fact]
    public void ShouldCapture_false_when_clock_moved_behind_last_capture()
    {
        // Jam perangkat sempat meleset ke depan (skew): jangan menambah capture dulu.
        Assert.False(ScreenshotPolicy.ShouldCapture(Now.AddMinutes(5), hasPendingCapture: false, Now));
    }
}
