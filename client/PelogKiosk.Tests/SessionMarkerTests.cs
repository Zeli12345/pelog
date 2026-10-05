using PelogKiosk.Core.Api;
using PelogKiosk.Core.Data;
using PelogKiosk.Core.Models;
using PelogKiosk.Core.Services;
using PelogKiosk.Core.Time;
using Xunit;

namespace PelogKiosk.Tests;

/// <summary>
/// Berkas penanda sesi (session.active) dipakai agen pembaruan SYSTEM untuk
/// menunda pemasangan update selama sesi berlangsung.
/// </summary>
public class SessionMarkerTests : IDisposable
{
    private readonly string _directory;

    private readonly LocalStore _store;

    private readonly SessionManager _sessions;

    public SessionMarkerTests()
    {
        _directory = Path.Combine(Path.GetTempPath(), "pelog-tests", Guid.NewGuid().ToString("N"));
        Directory.CreateDirectory(_directory);
        _store = new LocalStore(Path.Combine(_directory, "local.db"));

        var clock = new ServerClock();
        var api = new PelogApiClient(new HttpClient(), clock, "http://127.0.0.1:9");
        _sessions = new SessionManager(_store, api, clock, _directory);
    }

    private string MarkerPath => Path.Combine(_directory, "session.active");

    public void Dispose()
    {
        _store.Dispose();

        try
        {
            Directory.Delete(_directory, recursive: true);
        }
        catch (IOException)
        {
            // abaikan sisa file terkunci
        }
    }

    [Fact]
    public void Marker_is_created_on_start_and_removed_on_close()
    {
        var record = _sessions.BeginStudent("0051234567", 1, "Praktikum");

        Assert.True(File.Exists(MarkerPath));
        Assert.Equal(record.SessionUuid, File.ReadAllText(MarkerPath));

        _sessions.Close(record, null, null, "normal");

        Assert.False(File.Exists(MarkerPath));
    }

    [Fact]
    public void Staff_session_also_creates_the_marker()
    {
        var record = _sessions.BeginStaff("198501152010011002", "Pemeliharaan");

        Assert.True(File.Exists(MarkerPath));

        _sessions.Close(record, null, null, "normal");
        Assert.False(File.Exists(MarkerPath));
    }

    [Fact]
    public void Recovery_with_no_open_session_removes_stale_marker()
    {
        File.WriteAllText(MarkerPath, "sesi-lama");

        var result = _sessions.RecoverOnStartup();

        Assert.Equal(RecoveryAction.None, result.Action);
        Assert.False(File.Exists(MarkerPath));
    }

    [Fact]
    public void Resumed_session_keeps_marker_active()
    {
        _store.SaveSession(OpenSession(DateTimeOffset.UtcNow));

        var result = _sessions.RecoverOnStartup();

        Assert.Equal(RecoveryAction.Resume, result.Action);
        Assert.True(File.Exists(MarkerPath));
    }

    [Fact]
    public void Stale_session_closed_as_recovery_removes_marker()
    {
        _store.SaveSession(OpenSession(DateTimeOffset.UtcNow.AddHours(-1)));

        var result = _sessions.RecoverOnStartup();

        Assert.Equal(RecoveryAction.ClosedAsRecovery, result.Action);
        Assert.False(File.Exists(MarkerPath));
    }

    private static LocalSessionRecord OpenSession(DateTimeOffset heartbeat) => new()
    {
        SessionUuid = Guid.NewGuid().ToString(),
        UserType = "student",
        Nisn = "0051234567",
        SubjectId = 1,
        UsagePurpose = "Praktikum",
        StartedAtClient = heartbeat,
        LastHeartbeatAt = heartbeat,
        State = "open",
    };
}
