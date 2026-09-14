using BalilogKiosk.Core.Time;
using Xunit;

namespace BalilogKiosk.Tests;

public class ServerClockTests
{
    [Fact]
    public void Sync_Applies_Server_Offset()
    {
        var localNow = new DateTimeOffset(2026, 9, 14, 10, 0, 0, TimeSpan.Zero);
        var clock = new ServerClock(() => localNow);

        Assert.Equal(localNow, clock.Now);
        Assert.False(clock.IsSynced);

        // Server 7 menit di depan
        clock.Sync(localNow.AddMinutes(7));

        Assert.True(clock.IsSynced);
        Assert.Equal(localNow.AddMinutes(7), clock.Now);
        Assert.Equal(TimeSpan.FromMinutes(7), clock.Offset);
    }

    [Fact]
    public void Sync_Ignores_Null_And_Keeps_Previous_Offset()
    {
        var localNow = new DateTimeOffset(2026, 9, 14, 10, 0, 0, TimeSpan.Zero);
        var clock = new ServerClock(() => localNow);

        clock.Sync(localNow.AddMinutes(2));
        clock.Sync(null);

        Assert.Equal(TimeSpan.FromMinutes(2), clock.Offset);
    }
}
