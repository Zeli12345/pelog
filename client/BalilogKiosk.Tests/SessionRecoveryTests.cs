using BalilogKiosk.Core.Api;
using BalilogKiosk.Core.Data;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Services;
using BalilogKiosk.Core.Time;
using Xunit;

namespace BalilogKiosk.Tests;

public class SessionRecoveryTests : IDisposable
{
    private readonly string _directory;

    private readonly LocalStore _store;

    private readonly SessionManager _sessions;

    public SessionRecoveryTests()
    {
        _directory = Path.Combine(Path.GetTempPath(), "balilog-tests", Guid.NewGuid().ToString("N"));
        _store = new LocalStore(Path.Combine(_directory, "local.db"));

        var clock = new ServerClock();
        var api = new BalilogApiClient(new HttpClient(), clock, "http://127.0.0.1:9");
        _sessions = new SessionManager(_store, api, clock);
    }

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
    public void Fresh_Session_Without_Shutdown_Pending_Resumes()
    {
        _store.SaveSession(OpenSession("11111111-1111-4111-8111-111111111111", DateTimeOffset.UtcNow));

        var result = _sessions.RecoverOnStartup();

        Assert.Equal(RecoveryAction.Resume, result.Action);
        Assert.Equal("11111111-1111-4111-8111-111111111111", result.Record!.SessionUuid);
    }

    [Fact]
    public void Shutdown_Pending_Closes_Session_As_Shutdown()
    {
        _store.SaveSession(OpenSession("22222222-2222-4222-8222-222222222222", DateTimeOffset.UtcNow));
        _store.SetKv("shutdown_pending", "22222222-2222-4222-8222-222222222222");

        var result = _sessions.RecoverOnStartup();

        Assert.Equal(RecoveryAction.ClosedAsRecovery, result.Action);
        Assert.Equal("shutdown", result.Record!.CloseReason);
        Assert.True(string.IsNullOrEmpty(_store.GetKv("shutdown_pending")));
        Assert.Empty(_store.GetOpenSessions());
    }

    [Fact]
    public void Stale_Shutdown_Pending_Is_Cleared()
    {
        _store.SetKv("shutdown_pending", "33333333-3333-4333-8333-333333333333");

        var result = _sessions.RecoverOnStartup();

        Assert.Equal(RecoveryAction.None, result.Action);
        Assert.True(string.IsNullOrEmpty(_store.GetKv("shutdown_pending")));
    }

    [Fact]
    public void Stale_Session_Closes_As_Recovery()
    {
        _store.SaveSession(OpenSession("44444444-4444-4444-8444-444444444444", DateTimeOffset.UtcNow.AddHours(-1)));

        var result = _sessions.RecoverOnStartup();

        Assert.Equal(RecoveryAction.ClosedAsRecovery, result.Action);
        Assert.Equal("recovery", result.Record!.CloseReason);
    }

    private static LocalSessionRecord OpenSession(string uuid, DateTimeOffset heartbeat) => new()
    {
        SessionUuid = uuid,
        UserType = "student",
        Nisn = "0051234567",
        SubjectId = 1,
        UsagePurpose = "Praktikum",
        StartedAtClient = heartbeat,
        LastHeartbeatAt = heartbeat,
        State = "open",
    };
}
