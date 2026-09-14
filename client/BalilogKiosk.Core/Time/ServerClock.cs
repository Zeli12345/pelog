namespace BalilogKiosk.Core.Time;

/// <summary>
/// Jam server: menyimpan selisih antara waktu server dan waktu lokal
/// agar pencatatan sesi tidak bergantung pada jam laptop yang bisa melenceng.
/// </summary>
public sealed class ServerClock
{
    private readonly Func<DateTimeOffset> _utcNow;
    private TimeSpan _offset;

    public ServerClock(Func<DateTimeOffset>? utcNowProvider = null)
    {
        _utcNow = utcNowProvider ?? (() => DateTimeOffset.UtcNow);
    }

    public DateTimeOffset Now => _utcNow() + _offset;

    public TimeSpan Offset => _offset;

    public bool IsSynced { get; private set; }

    public void Sync(DateTimeOffset? serverTime)
    {
        if (serverTime is null)
        {
            return;
        }

        _offset = serverTime.Value - _utcNow();
        IsSynced = true;
    }
}
