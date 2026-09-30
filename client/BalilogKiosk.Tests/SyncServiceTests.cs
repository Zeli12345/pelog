using System.Net;
using System.Text;
using System.Text.Json;
using BalilogKiosk.Core.Api;
using BalilogKiosk.Core.Data;
using BalilogKiosk.Core.Services;
using BalilogKiosk.Core.Time;
using Xunit;

namespace BalilogKiosk.Tests;

public class SyncServiceTests : IDisposable
{
    private readonly string _directory;

    private readonly LocalStore _store;

    public SyncServiceTests()
    {
        _directory = Path.Combine(Path.GetTempPath(), "balilog-tests", Guid.NewGuid().ToString("N"));
        _store = new LocalStore(Path.Combine(_directory, "local.db"));
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
    public async Task Server_Error_Stops_Retrying_After_Three_Attempts()
    {
        const string uuid = "55555555-5555-4555-8555-555555555555";
        SaveClosedSession(uuid);

        var sync = CreateSync(new SyncStatusHandler(uuid, "error"));

        // Dua percobaan pertama masih dianggap bisa dicoba lagi...
        Assert.Equal(0, await sync.PushSessionsAsync());
        Assert.Single(_store.GetPendingSessions());
        Assert.Equal(1, _store.GetSession(uuid)!.SyncFailedAttempts);

        Assert.Equal(0, await sync.PushSessionsAsync());
        Assert.Single(_store.GetPendingSessions());
        Assert.Equal(2, _store.GetSession(uuid)!.SyncFailedAttempts);

        // ...percobaan ketiga menandai sesi berhenti dikirim (abandoned).
        Assert.Equal(0, await sync.PushSessionsAsync());
        Assert.Empty(_store.GetPendingSessions());
        Assert.Equal("failed", _store.GetSession(uuid)!.State);
    }

    [Theory]
    [InlineData("created")]
    [InlineData("skipped")]
    public async Task Success_Status_Marks_Session_Synced(string status)
    {
        const string uuid = "66666666-6666-4666-8666-666666666666";
        SaveClosedSession(uuid);

        var sync = CreateSync(new SyncStatusHandler(uuid, status));

        Assert.Equal(1, await sync.PushSessionsAsync());
        Assert.Empty(_store.GetPendingSessions());
        Assert.Equal("synced", _store.GetSession(uuid)!.State);
    }

    private void SaveClosedSession(string uuid)
    {
        _store.SaveSession(new LocalSessionRecord
        {
            SessionUuid = uuid,
            UserType = "student",
            Nisn = "0051234567",
            SubjectId = 1,
            UsagePurpose = "Praktikum",
            StartedAtClient = DateTimeOffset.UtcNow.AddMinutes(-30),
            LastHeartbeatAt = DateTimeOffset.UtcNow,
            EndedAtClient = DateTimeOffset.UtcNow,
            CloseReason = "recovery",
            State = "closed",
        });
    }

    private SyncService CreateSync(HttpMessageHandler handler)
    {
        var clock = new ServerClock();

        return new SyncService(
            _store,
            new BalilogApiClient(new HttpClient(handler), clock, "http://balilog.test"),
            clock);
    }

    private sealed class SyncStatusHandler : HttpMessageHandler
    {
        private readonly string _sessionUuid;

        private readonly string _status;

        public SyncStatusHandler(string sessionUuid, string status)
        {
            _sessionUuid = sessionUuid;
            _status = status;
        }

        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            var body = JsonSerializer.Serialize(new
            {
                ok = true,
                data = new
                {
                    results = new[]
                    {
                        new { session_uuid = _sessionUuid, status = _status },
                    },
                },
            });

            return Task.FromResult(new HttpResponseMessage(HttpStatusCode.OK)
            {
                Content = new StringContent(body, Encoding.UTF8, "application/json"),
            });
        }
    }
}
