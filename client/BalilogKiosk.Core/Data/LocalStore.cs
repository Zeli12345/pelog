using BalilogKiosk.Core.Models;
using Microsoft.Data.Sqlite;

namespace BalilogKiosk.Core.Data;

public sealed class LocalSessionRecord
{
    public string SessionUuid { get; set; } = string.Empty;

    public string UserType { get; set; } = "student";

    public string? Nisn { get; set; }

    public string? NipId { get; set; }

    public long? SubjectId { get; set; }

    public string UsagePurpose { get; set; } = string.Empty;

    public DateTimeOffset? StartedAtClient { get; set; }

    public DateTimeOffset? LastHeartbeatAt { get; set; }

    public DateTimeOffset? EndedAtClient { get; set; }

    public string? Feedback { get; set; }

    public string? Comprehension { get; set; }

    public string CloseReason { get; set; } = "normal";

    /// <summary>open | closed | synced</summary>
    public string State { get; set; } = "open";

    public string? ScreenshotPath { get; set; }

    /// <summary>none | pending | synced</summary>
    public string ScreenshotState { get; set; } = "none";

    public DateTimeOffset CreatedAt { get; set; } = DateTimeOffset.UtcNow;

    public bool IsOpen => State == "open";
}

/// <summary>
/// Basis data lokal (SQLite) untuk cache siswa/guru/mapel dan antrean sinkronisasi.
/// Semua operasi bersifat lokal-first: aplikasi tetap berjalan walau server mati.
/// </summary>
public sealed class LocalStore : IDisposable
{
    private readonly SqliteConnection _connection;

    public LocalStore(string databasePath)
    {
        var directory = Path.GetDirectoryName(databasePath);

        if (!string.IsNullOrEmpty(directory))
        {
            Directory.CreateDirectory(directory);
        }

        _connection = new SqliteConnection($"Data Source={databasePath}");
        _connection.Open();
        EnsureSchema();
    }

    private void EnsureSchema()
    {
        Execute(@"
            PRAGMA journal_mode=WAL;

            CREATE TABLE IF NOT EXISTS kv (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS students (
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
            );

            CREATE TABLE IF NOT EXISTS staff (
                nip_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                role TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS subjects (
                id INTEGER PRIMARY KEY,
                code TEXT NOT NULL,
                name TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS sessions (
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
            );");
    }

    private void Execute(string sql)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = sql;
        command.ExecuteNonQuery();
    }

    private static string? Iso(DateTimeOffset? value) => value?.ToUniversalTime().ToString("o");

    private static DateTimeOffset? ParseIso(string? value) =>
        string.IsNullOrEmpty(value) ? null : DateTimeOffset.Parse(value);

    // ---------- Key-value ----------

    public string? GetKv(string key)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT value FROM kv WHERE key = $key";
        command.Parameters.AddWithValue("$key", key);
        var result = command.ExecuteScalar();

        return result as string;
    }

    public void SetKv(string key, string value)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "INSERT INTO kv (key, value) VALUES ($key, $value) ON CONFLICT(key) DO UPDATE SET value = excluded.value";
        command.Parameters.AddWithValue("$key", key);
        command.Parameters.AddWithValue("$value", value);
        command.ExecuteNonQuery();
    }

    // ---------- Cache master data ----------

    public void ReplaceStudents(IEnumerable<CachedStudent> students)
    {
        using var transaction = _connection.BeginTransaction();

        using (var delete = _connection.CreateCommand())
        {
            delete.Transaction = transaction;
            delete.CommandText = "DELETE FROM students";
            delete.ExecuteNonQuery();
        }

        foreach (var student in students)
        {
            using var insert = _connection.CreateCommand();
            insert.Transaction = transaction;
            insert.CommandText = @"
                INSERT INTO students (nisn, name, class, has_pin, pin_algo, pin_salt, pin_iterations, pin_hash, pin_failed, pin_locked_until)
                VALUES ($nisn, $name, $class, $hasPin, $algo, $salt, $iterations, $hash, 0, NULL)";
            insert.Parameters.AddWithValue("$nisn", student.Nisn);
            insert.Parameters.AddWithValue("$name", student.Name);
            insert.Parameters.AddWithValue("$class", student.ClassName);
            insert.Parameters.AddWithValue("$hasPin", student.HasPin ? 1 : 0);
            insert.Parameters.AddWithValue("$algo", (object?)student.Pin?.Algo ?? DBNull.Value);
            insert.Parameters.AddWithValue("$salt", (object?)student.Pin?.Salt ?? DBNull.Value);
            insert.Parameters.AddWithValue("$iterations", (object?)student.Pin?.Iterations ?? DBNull.Value);
            insert.Parameters.AddWithValue("$hash", (object?)student.Pin?.Hash ?? DBNull.Value);
            insert.ExecuteNonQuery();
        }

        transaction.Commit();
    }

    public void ReplaceStaff(IEnumerable<CachedStaff> staff)
    {
        using var transaction = _connection.BeginTransaction();

        using (var delete = _connection.CreateCommand())
        {
            delete.Transaction = transaction;
            delete.CommandText = "DELETE FROM staff";
            delete.ExecuteNonQuery();
        }

        foreach (var member in staff)
        {
            using var insert = _connection.CreateCommand();
            insert.Transaction = transaction;
            insert.CommandText = "INSERT INTO staff (nip_id, name, role) VALUES ($nip, $name, $role)";
            insert.Parameters.AddWithValue("$nip", member.NipId);
            insert.Parameters.AddWithValue("$name", member.Name);
            insert.Parameters.AddWithValue("$role", member.Role);
            insert.ExecuteNonQuery();
        }

        transaction.Commit();
    }

    public void ReplaceSubjects(IEnumerable<CachedSubject> subjects)
    {
        using var transaction = _connection.BeginTransaction();

        using (var delete = _connection.CreateCommand())
        {
            delete.Transaction = transaction;
            delete.CommandText = "DELETE FROM subjects";
            delete.ExecuteNonQuery();
        }

        foreach (var subject in subjects)
        {
            using var insert = _connection.CreateCommand();
            insert.Transaction = transaction;
            insert.CommandText = "INSERT INTO subjects (id, code, name) VALUES ($id, $code, $name)";
            insert.Parameters.AddWithValue("$id", subject.Id);
            insert.Parameters.AddWithValue("$code", subject.Code);
            insert.Parameters.AddWithValue("$name", subject.Name);
            insert.ExecuteNonQuery();
        }

        transaction.Commit();
    }

    public CachedStudent? GetStudent(string nisn)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT * FROM students WHERE nisn = $nisn";
        command.Parameters.AddWithValue("$nisn", nisn);

        using var reader = command.ExecuteReader();

        if (!reader.Read())
        {
            return null;
        }

        var hasPin = reader.GetInt32(reader.GetOrdinal("has_pin")) == 1;

        return new CachedStudent
        {
            Nisn = reader.GetString(reader.GetOrdinal("nisn")),
            Name = reader.GetString(reader.GetOrdinal("name")),
            ClassName = reader.GetString(reader.GetOrdinal("class")),
            HasPin = hasPin,
            Pin = hasPin ? new PinData
            {
                Algo = reader.IsDBNull(reader.GetOrdinal("pin_algo")) ? null : reader.GetString(reader.GetOrdinal("pin_algo")),
                Salt = reader.IsDBNull(reader.GetOrdinal("pin_salt")) ? null : reader.GetString(reader.GetOrdinal("pin_salt")),
                Iterations = reader.IsDBNull(reader.GetOrdinal("pin_iterations")) ? 0 : reader.GetInt32(reader.GetOrdinal("pin_iterations")),
                Hash = reader.IsDBNull(reader.GetOrdinal("pin_hash")) ? null : reader.GetString(reader.GetOrdinal("pin_hash")),
            } : null,
        };
    }

    public CachedStaff? GetStaff(string nipId)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT * FROM staff WHERE nip_id = $nip";
        command.Parameters.AddWithValue("$nip", nipId);

        using var reader = command.ExecuteReader();

        return reader.Read()
            ? new CachedStaff
            {
                NipId = reader.GetString(reader.GetOrdinal("nip_id")),
                Name = reader.GetString(reader.GetOrdinal("name")),
                Role = reader.GetString(reader.GetOrdinal("role")),
            }
            : null;
    }

    public List<CachedSubject> GetSubjects()
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT id, code, name FROM subjects ORDER BY name";

        using var reader = command.ExecuteReader();
        var result = new List<CachedSubject>();

        while (reader.Read())
        {
            result.Add(new CachedSubject
            {
                Id = reader.GetInt64(0),
                Code = reader.GetString(1),
                Name = reader.GetString(2),
            });
        }

        return result;
    }

    public int CountStudents()
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT COUNT(*) FROM students";

        return Convert.ToInt32(command.ExecuteScalar());
    }

    // ---------- PIN state lokal ----------

    public (int Failed, DateTimeOffset? LockedUntil) GetPinState(string nisn)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT pin_failed, pin_locked_until FROM students WHERE nisn = $nisn";
        command.Parameters.AddWithValue("$nisn", nisn);

        using var reader = command.ExecuteReader();

        if (!reader.Read())
        {
            return (0, null);
        }

        return (
            reader.GetInt32(0),
            reader.IsDBNull(1) ? null : DateTimeOffset.Parse(reader.GetString(1)));
    }

    public void SavePinState(string nisn, int failed, DateTimeOffset? lockedUntil)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "UPDATE students SET pin_failed = $failed, pin_locked_until = $locked WHERE nisn = $nisn";
        command.Parameters.AddWithValue("$failed", failed);
        command.Parameters.AddWithValue("$locked", (object?)Iso(lockedUntil) ?? DBNull.Value);
        command.Parameters.AddWithValue("$nisn", nisn);
        command.ExecuteNonQuery();
    }

    /// <summary>Menandai PIN siswa sudah dibuat (dipakai setelah set berhasil/antrean).</summary>
    public void UpdateStudentPin(string nisn, string algo, string salt, int iterations, string hash)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = @"
            UPDATE students
            SET has_pin = 1, pin_algo = $algo, pin_salt = $salt, pin_iterations = $iterations, pin_hash = $hash,
                pin_failed = 0, pin_locked_until = NULL
            WHERE nisn = $nisn";
        command.Parameters.AddWithValue("$algo", algo);
        command.Parameters.AddWithValue("$salt", salt);
        command.Parameters.AddWithValue("$iterations", iterations);
        command.Parameters.AddWithValue("$hash", hash);
        command.Parameters.AddWithValue("$nisn", nisn);
        command.ExecuteNonQuery();
    }

    // ---------- Sesi lokal ----------

    public void SaveSession(LocalSessionRecord record)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = @"
            INSERT INTO sessions (
                session_uuid, user_type, nisn, nip_id, subject_id, purpose,
                started_at_client, last_heartbeat_at, ended_at_client,
                feedback, comprehension, close_reason, state,
                screenshot_path, screenshot_state, created_at
            ) VALUES (
                $uuid, $userType, $nisn, $nip, $subjectId, $purpose,
                $started, $heartbeat, $ended,
                $feedback, $comprehension, $reason, $state,
                $shotPath, $shotState, $created
            )
            ON CONFLICT(session_uuid) DO UPDATE SET
                last_heartbeat_at = excluded.last_heartbeat_at,
                ended_at_client = excluded.ended_at_client,
                feedback = excluded.feedback,
                comprehension = excluded.comprehension,
                close_reason = excluded.close_reason,
                state = excluded.state,
                screenshot_path = excluded.screenshot_path,
                screenshot_state = excluded.screenshot_state";
        command.Parameters.AddWithValue("$uuid", record.SessionUuid);
        command.Parameters.AddWithValue("$userType", record.UserType);
        command.Parameters.AddWithValue("$nisn", (object?)record.Nisn ?? DBNull.Value);
        command.Parameters.AddWithValue("$nip", (object?)record.NipId ?? DBNull.Value);
        command.Parameters.AddWithValue("$subjectId", (object?)record.SubjectId ?? DBNull.Value);
        command.Parameters.AddWithValue("$purpose", record.UsagePurpose);
        command.Parameters.AddWithValue("$started", (object?)Iso(record.StartedAtClient) ?? DBNull.Value);
        command.Parameters.AddWithValue("$heartbeat", (object?)Iso(record.LastHeartbeatAt) ?? DBNull.Value);
        command.Parameters.AddWithValue("$ended", (object?)Iso(record.EndedAtClient) ?? DBNull.Value);
        command.Parameters.AddWithValue("$feedback", (object?)record.Feedback ?? DBNull.Value);
        command.Parameters.AddWithValue("$comprehension", (object?)record.Comprehension ?? DBNull.Value);
        command.Parameters.AddWithValue("$reason", record.CloseReason);
        command.Parameters.AddWithValue("$state", record.State);
        command.Parameters.AddWithValue("$shotPath", (object?)record.ScreenshotPath ?? DBNull.Value);
        command.Parameters.AddWithValue("$shotState", record.ScreenshotState);
        command.Parameters.AddWithValue("$created", Iso(record.CreatedAt) ?? string.Empty);
        command.ExecuteNonQuery();
    }

    public LocalSessionRecord? GetSession(string sessionUuid)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT * FROM sessions WHERE session_uuid = $uuid";
        command.Parameters.AddWithValue("$uuid", sessionUuid);

        using var reader = command.ExecuteReader();

        return reader.Read() ? MapSession(reader) : null;
    }

    public List<LocalSessionRecord> GetOpenSessions()
    {
        return QuerySessions("SELECT * FROM sessions WHERE state = 'open' ORDER BY created_at");
    }

    public List<LocalSessionRecord> GetPendingSessions(int limit = 100)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT * FROM sessions WHERE state = 'closed' ORDER BY created_at LIMIT $limit";
        command.Parameters.AddWithValue("$limit", limit);

        using var reader = command.ExecuteReader();
        var result = new List<LocalSessionRecord>();

        while (reader.Read())
        {
            result.Add(MapSession(reader));
        }

        return result;
    }

    public List<LocalSessionRecord> GetPendingScreenshots(int limit = 50)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT * FROM sessions WHERE screenshot_state = 'pending' AND screenshot_path IS NOT NULL ORDER BY created_at LIMIT $limit";
        command.Parameters.AddWithValue("$limit", limit);

        using var reader = command.ExecuteReader();
        var result = new List<LocalSessionRecord>();

        while (reader.Read())
        {
            result.Add(MapSession(reader));
        }

        return result;
    }

    public void MarkSessionSynced(string sessionUuid)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "UPDATE sessions SET state = 'synced' WHERE session_uuid = $uuid";
        command.Parameters.AddWithValue("$uuid", sessionUuid);
        command.ExecuteNonQuery();
    }

    public void MarkScreenshotSynced(string sessionUuid)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "UPDATE sessions SET screenshot_state = 'synced' WHERE session_uuid = $uuid";
        command.Parameters.AddWithValue("$uuid", sessionUuid);
        command.ExecuteNonQuery();
    }

    private List<LocalSessionRecord> QuerySessions(string sql)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = sql;

        using var reader = command.ExecuteReader();
        var result = new List<LocalSessionRecord>();

        while (reader.Read())
        {
            result.Add(MapSession(reader));
        }

        return result;
    }

    private static LocalSessionRecord MapSession(SqliteDataReader reader)
    {
        string? GetNullable(string column) =>
            reader.IsDBNull(reader.GetOrdinal(column)) ? null : reader.GetString(reader.GetOrdinal(column));

        return new LocalSessionRecord
        {
            SessionUuid = reader.GetString(reader.GetOrdinal("session_uuid")),
            UserType = reader.GetString(reader.GetOrdinal("user_type")),
            Nisn = GetNullable("nisn"),
            NipId = GetNullable("nip_id"),
            SubjectId = reader.IsDBNull(reader.GetOrdinal("subject_id")) ? null : reader.GetInt64(reader.GetOrdinal("subject_id")),
            UsagePurpose = reader.GetString(reader.GetOrdinal("purpose")),
            StartedAtClient = ParseIso(GetNullable("started_at_client")),
            LastHeartbeatAt = ParseIso(GetNullable("last_heartbeat_at")),
            EndedAtClient = ParseIso(GetNullable("ended_at_client")),
            Feedback = GetNullable("feedback"),
            Comprehension = GetNullable("comprehension"),
            CloseReason = reader.GetString(reader.GetOrdinal("close_reason")),
            State = reader.GetString(reader.GetOrdinal("state")),
            ScreenshotPath = GetNullable("screenshot_path"),
            ScreenshotState = reader.GetString(reader.GetOrdinal("screenshot_state")),
            CreatedAt = ParseIso(GetNullable("created_at")) ?? DateTimeOffset.UtcNow,
        };
    }

    public void Dispose()
    {
        _connection.Dispose();
    }
}
