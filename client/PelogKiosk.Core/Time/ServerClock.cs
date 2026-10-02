namespace PelogKiosk.Core.Time;

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

    /// <summary>Waktu lokal mentah (tanpa offset server) untuk mengukur awal round-trip.</summary>
    public DateTimeOffset LocalNow => _utcNow();

    /// <summary>
    /// Menerapkan waktu server relatif terhadap titik tengah round-trip
    /// (bila <paramref name="requestStartedAt"/> diberikan), sehingga offset
    /// tidak melenceng sebesar setengah RTT.
    /// </summary>
    public void Sync(DateTimeOffset? serverTime, DateTimeOffset? requestStartedAt = null)
    {
        if (serverTime is null)
        {
            return;
        }

        var completedAt = _utcNow();
        var clientReference = requestStartedAt is { } startedAt && completedAt > startedAt
            ? startedAt + (completedAt - startedAt) / 2
            : completedAt;

        _offset = serverTime.Value - clientReference;
        IsSynced = true;
    }
}
