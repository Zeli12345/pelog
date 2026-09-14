using BalilogKiosk.Core.Data;
using BalilogKiosk.Core.Models;
using Xunit;

namespace BalilogKiosk.Tests;

public class LocalStoreTests : IDisposable
{
    private readonly string _directory;

    private readonly LocalStore _store;

    public LocalStoreTests()
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
    public void Kv_Store_Roundtrip()
    {
        Assert.Null(_store.GetKv("missing"));

        _store.SetKv("enrollment_code", "BLG-TEST-1234");
        Assert.Equal("BLG-TEST-1234", _store.GetKv("enrollment_code"));

        _store.SetKv("enrollment_code", "BLG-NEW-5678");
        Assert.Equal("BLG-NEW-5678", _store.GetKv("enrollment_code"));
    }

    [Fact]
    public void Students_Cache_With_Pin_Data()
    {
        _store.ReplaceStudents([
            new CachedStudent
            {
                Nisn = "0051234567",
                Name = "Budi Pratama",
                ClassName = "X RPL 1",
                HasPin = true,
                Pin = new PinData
                {
                    Algo = "pbkdf2-sha256",
                    Salt = "ASNFZ4mrze8BI0VniavN7w==",
                    Iterations = 100_000,
                    Hash = "6kZ06H8QhrB5sp7U3GVttvbAULcRQqyOeecRKr80AH8=",
                },
            },
            new CachedStudent
            {
                Nisn = "0059999999",
                Name = "Tanpa Pin",
                ClassName = "X TKJ 1",
                HasPin = false,
            },
        ]);

        Assert.Equal(2, _store.CountStudents());

        var withPin = _store.GetStudent("0051234567");
        Assert.NotNull(withPin);
        Assert.True(withPin!.HasPin);
        Assert.Equal("Budi Pratama", withPin.Name);
        Assert.Equal("pbkdf2-sha256", withPin.Pin!.Algo);
        Assert.Equal(100_000, withPin.Pin.Iterations);

        var withoutPin = _store.GetStudent("0059999999");
        Assert.NotNull(withoutPin);
        Assert.False(withoutPin!.HasPin);
        Assert.Null(withoutPin.Pin);

        Assert.Null(_store.GetStudent("0000000000"));
    }

    [Fact]
    public void Staff_And_Subjects_Cache()
    {
        _store.ReplaceStaff([
            new CachedStaff { NipId = "198501152010011002", Name = "I Komang Purwata", Role = "teacher" },
        ]);
        _store.ReplaceSubjects([
            new CachedSubject { Id = 1, Code = "PWPB", Name = "Pemrograman Web" },
            new CachedSubject { Id = 2, Code = "BD", Name = "Basis Data" },
        ]);

        Assert.Equal("I Komang Purwata", _store.GetStaff("198501152010011002")!.Name);
        Assert.Null(_store.GetStaff("000"));

        var subjects = _store.GetSubjects();
        Assert.Equal(2, subjects.Count);
        Assert.Equal("Basis Data", subjects[0].Name);
    }

    [Fact]
    public void Pin_State_Local_Lock_Tracking()
    {
        _store.ReplaceStudents([
            new CachedStudent { Nisn = "0051234567", Name = "Budi", ClassName = "X RPL 1", HasPin = true },
        ]);

        var lockedUntil = DateTimeOffset.UtcNow.AddMinutes(5);
        _store.SavePinState("0051234567", 5, lockedUntil);

        var (failed, locked) = _store.GetPinState("0051234567");

        Assert.Equal(5, failed);
        Assert.NotNull(locked);
        Assert.Equal(lockedUntil.ToUnixTimeSeconds(), locked!.Value.ToUnixTimeSeconds());
    }

    [Fact]
    public void Session_Lifecycle_And_Sync_Queue()
    {
        var session = new LocalSessionRecord
        {
            SessionUuid = "11111111-1111-4111-8111-111111111111",
            UserType = "student",
            Nisn = "0051234567",
            SubjectId = 1,
            UsagePurpose = "Praktikum",
            StartedAtClient = DateTimeOffset.UtcNow.AddMinutes(-30),
            LastHeartbeatAt = DateTimeOffset.UtcNow,
        };

        _store.SaveSession(session);

        var open = _store.GetOpenSessions();
        Assert.Single(open);
        Assert.Equal("Praktikum", open[0].UsagePurpose);

        session.State = "closed";
        session.EndedAtClient = DateTimeOffset.UtcNow;
        session.Feedback = "Belajar layout";
        session.Comprehension = "paham";
        _store.SaveSession(session);

        Assert.Empty(_store.GetOpenSessions());

        var pending = _store.GetPendingSessions();
        Assert.Single(pending);
        Assert.Equal("paham", pending[0].Comprehension);

        _store.MarkSessionSynced(session.SessionUuid);
        Assert.Empty(_store.GetPendingSessions());
    }

    [Fact]
    public void Screenshot_Queue_Flow()
    {
        var session = new LocalSessionRecord
        {
            SessionUuid = "22222222-2222-4222-8222-222222222222",
            UserType = "student",
            Nisn = "0051234567",
            UsagePurpose = "Praktikum",
            StartedAtClient = DateTimeOffset.UtcNow,
        };

        _store.SaveSession(session);
        session.ScreenshotPath = "C:\\temp\\shot.webp";
        session.ScreenshotState = "pending";
        _store.SaveSession(session);

        var pending = _store.GetPendingScreenshots();
        Assert.Single(pending);
        Assert.Equal("C:\\temp\\shot.webp", pending[0].ScreenshotPath);

        _store.MarkScreenshotSynced(session.SessionUuid);
        Assert.Empty(_store.GetPendingScreenshots());
    }
}
