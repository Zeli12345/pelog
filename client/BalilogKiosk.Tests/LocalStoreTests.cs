using BalilogKiosk.Core.Data;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Security;
using Microsoft.Data.Sqlite;
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
    public void Students_Cache_Roundtrip_With_Birth_Date()
    {
        _store.ReplaceStudents([
            new CachedStudent
            {
                Nisn = "0051234567",
                Name = "Budi Pratama",
                ClassName = "X RPL 1",
                BirthDate = new DateOnly(2008, 7, 14),
            },
            new CachedStudent
            {
                Nisn = "0059999999",
                Name = "Ani Wijaya",
                ClassName = "X TKJ 1",
            },
        ]);

        Assert.Equal(2, _store.CountStudents());

        var budi = _store.GetStudent("0051234567");
        Assert.NotNull(budi);
        Assert.Equal("Budi Pratama", budi!.Name);
        Assert.Equal("X RPL 1", budi.ClassName);
        Assert.Equal(new DateOnly(2008, 7, 14), budi.BirthDate);

        var ani = _store.GetStudent("0059999999");
        Assert.NotNull(ani);
        Assert.Equal("Ani Wijaya", ani!.Name);
        Assert.Null(ani.BirthDate);

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
    public void Students_Are_Encrypted_At_Rest_And_Decrypted_On_Read()
    {
        _store.ReplaceStudents([
            new CachedStudent
            {
                Nisn = "0051234567",
                Name = "Budi Pratama",
                ClassName = "X RPL 1",
                BirthDate = new DateOnly(2008, 7, 14),
            },
        ]);

        var raw = ReadRawStudent();

        // Nilai mentah di basis data harus terenkripsi dan tidak memuat data asli.
        Assert.StartsWith(StudentDataProtector.Prefix, raw.Nisn);
        Assert.StartsWith(StudentDataProtector.Prefix, raw.Name);
        Assert.StartsWith(StudentDataProtector.Prefix, raw.Class);
        Assert.NotNull(raw.BirthDate);
        Assert.StartsWith(StudentDataProtector.Prefix, raw.BirthDate!);
        Assert.DoesNotContain("0051234567", raw.Nisn);
        Assert.DoesNotContain("Budi Pratama", raw.Name);
        Assert.DoesNotContain("2008-07-14", raw.BirthDate!);

        var student = _store.GetStudent("0051234567");

        Assert.NotNull(student);
        Assert.Equal("0051234567", student!.Nisn);
        Assert.Equal("Budi Pratama", student.Name);
        Assert.Equal("X RPL 1", student.ClassName);
        Assert.Equal(new DateOnly(2008, 7, 14), student.BirthDate);
    }

    [Fact]
    public void Legacy_Plaintext_Student_Rows_Remain_Readable()
    {
        using (var connection = new SqliteConnection($"Data Source={Path.Combine(_directory, "local.db")}"))
        {
            connection.Open();

            using var command = connection.CreateCommand();
            command.CommandText = @"
                INSERT INTO students (nisn, name, class, birth_date)
                VALUES ('0051234567', 'Budi Lama', 'X RPL 9', '2007-01-02')";
            command.ExecuteNonQuery();
        }

        var student = _store.GetStudent("0051234567");

        Assert.NotNull(student);
        Assert.Equal("Budi Lama", student!.Name);
        Assert.Equal("X RPL 9", student.ClassName);
        Assert.Equal(new DateOnly(2007, 1, 2), student.BirthDate);
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

    [Fact]
    public void Opening_Old_Database_Upgrades_Student_Columns_And_Drops_Pin()
    {
        var databasePath = Path.Combine(_directory, "legacy-students.db");

        using (var connection = new SqliteConnection($"Data Source={databasePath}"))
        {
            connection.Open();

            using var command = connection.CreateCommand();
            command.CommandText = @"
                CREATE TABLE students (
                    nisn TEXT PRIMARY KEY,
                    name TEXT NOT NULL,
                    class TEXT NOT NULL,
                    has_pin INTEGER NOT NULL DEFAULT 0,
                    pin_algo TEXT,
                    pin_salt TEXT,
                    pin_iterations INTEGER,
                    pin_hash TEXT,
                    pin_failed INTEGER NOT NULL DEFAULT 0,
                    pin_locked_until TEXT
                )";
            command.ExecuteNonQuery();
        }

        using var store = new LocalStore(databasePath);

        store.ReplaceStudents([
            new CachedStudent
            {
                Nisn = "0051234567",
                Name = "Budi",
                ClassName = "X RPL 1",
                BirthDate = new DateOnly(2008, 7, 14),
            },
        ]);

        Assert.Equal(new DateOnly(2008, 7, 14), store.GetStudent("0051234567")!.BirthDate);

        using var check = new SqliteConnection($"Data Source={databasePath}");
        check.Open();

        using var columns = check.CreateCommand();
        columns.CommandText = "PRAGMA table_info(students)";

        var names = new List<string>();

        using (var reader = columns.ExecuteReader())
        {
            while (reader.Read())
            {
                names.Add(reader.GetString(1));
            }
        }

        Assert.Contains("birth_date", names);
        Assert.DoesNotContain(names, name =>
            name.StartsWith("pin_", StringComparison.Ordinal) ||
            string.Equals(name, "has_pin", StringComparison.Ordinal));
    }

    [Fact]
    public void Opening_Old_Database_Upgrades_Session_Columns()
    {
        var databasePath = Path.Combine(_directory, "legacy.db");

        using (var connection = new SqliteConnection($"Data Source={databasePath}"))
        {
            connection.Open();

            using var command = connection.CreateCommand();
            command.CommandText = @"
                CREATE TABLE sessions (
                    session_uuid TEXT PRIMARY KEY,
                    user_type TEXT NOT NULL,
                    nisn TEXT,
                    nip_id TEXT,
                    subject_id INTEGER,
                    purpose TEXT NOT NULL,
                    started_at_client TEXT,
                    last_heartbeat_at TEXT,
                    ended_at_client TEXT,
                    feedback TEXT,
                    comprehension TEXT,
                    close_reason TEXT NOT NULL DEFAULT 'normal',
                    state TEXT NOT NULL DEFAULT 'open',
                    screenshot_path TEXT,
                    screenshot_state TEXT NOT NULL DEFAULT 'none',
                    created_at TEXT NOT NULL
                )";
            command.ExecuteNonQuery();
        }

        using var store = new LocalStore(databasePath);
        var uuid = "77777777-7777-4777-8777-777777777777";
        var capturedAt = DateTimeOffset.UtcNow.AddMinutes(-2);

        store.SaveSession(new LocalSessionRecord
        {
            SessionUuid = uuid,
            UserType = "student",
            Nisn = "0051234567",
            UsagePurpose = "Praktikum",
            StartedAtClient = DateTimeOffset.UtcNow,
            ScreenshotPath = "C:\\temp\\shot.webp",
            ScreenshotState = "pending",
            ScreenshotCapturedAt = capturedAt,
        });

        var record = store.GetSession(uuid);

        Assert.NotNull(record);
        Assert.NotNull(record!.ScreenshotCapturedAt);
        Assert.Equal(capturedAt.ToUnixTimeSeconds(), record.ScreenshotCapturedAt!.Value.ToUnixTimeSeconds());
        Assert.Equal(0, record!.SyncFailedAttempts);
    }

    [Fact]
    public void Screenshot_Capture_Time_And_Sync_Attempts_Roundtrip()
    {
        var uuid = "33333333-3333-4333-8333-333333333333";
        var capturedAt = DateTimeOffset.UtcNow.AddMinutes(-7);

        _store.SaveSession(new LocalSessionRecord
        {
            SessionUuid = uuid,
            UserType = "student",
            Nisn = "0051234567",
            UsagePurpose = "Praktikum",
            StartedAtClient = DateTimeOffset.UtcNow.AddMinutes(-30),
            LastHeartbeatAt = DateTimeOffset.UtcNow,
            EndedAtClient = DateTimeOffset.UtcNow,
            CloseReason = "recovery",
            State = "closed",
            ScreenshotPath = "C:\\temp\\shot.webp",
            ScreenshotState = "pending",
            ScreenshotCapturedAt = capturedAt,
        });

        var queued = _store.GetPendingScreenshots()[0];

        Assert.NotNull(queued.ScreenshotCapturedAt);
        Assert.Equal(capturedAt.ToUnixTimeSeconds(), queued.ScreenshotCapturedAt!.Value.ToUnixTimeSeconds());

        Assert.Single(_store.GetPendingSessions());
        Assert.Equal(1, _store.IncrementSyncAttempts(uuid));
        Assert.Equal(2, _store.IncrementSyncAttempts(uuid));
        Assert.Equal(2, _store.GetSession(uuid)!.SyncFailedAttempts);

        _store.MarkSessionSyncFailed(uuid);

        Assert.Equal("failed", _store.GetSession(uuid)!.State);
        Assert.Empty(_store.GetPendingSessions());
    }

    private (string Nisn, string Name, string Class, string? BirthDate) ReadRawStudent()
    {
        using var connection = new SqliteConnection($"Data Source={Path.Combine(_directory, "local.db")}");
        connection.Open();

        using var command = connection.CreateCommand();
        command.CommandText = "SELECT nisn, name, class, birth_date FROM students LIMIT 1";

        using var reader = command.ExecuteReader();
        reader.Read();

        return (
            reader.GetString(0),
            reader.GetString(1),
            reader.GetString(2),
            reader.IsDBNull(3) ? null : reader.GetString(3));
    }
}
