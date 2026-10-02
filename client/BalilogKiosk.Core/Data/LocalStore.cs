using System.Globalization;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Security;
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

    /// <summary>open | closed | synced | failed</summary>
    public string State { get; set; } = "open";

    public string? ScreenshotPath { get; set; }

    /// <summary>none | pending | synced</summary>
    public string ScreenshotState { get; set; } = "none";

    /// <summary>Waktu nyata saat screenshot diambil (null untuk data lama).</summary>
    public DateTimeOffset? ScreenshotCapturedAt { get; set; }

    /// <summary>Jumlah percobaan sinkronisasi yang gagal (membatasi retry antrean).</summary>
    public int SyncFailedAttempts { get; set; }

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

    private readonly StudentDataProtector _protector;

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
        _protector = new StudentDataProtector(GetKv, SetKv);
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
                birth_date TEXT
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
                screenshot_captured_at TEXT,
                sync_failed_attempts INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );");

        // Upgrade idempoten untuk basis data lama yang belum memiliki kolom di atas.
        EnsureColumn("sessions", "screenshot_captured_at", "TEXT");
        EnsureColumn("sessions", "sync_failed_attempts", "INTEGER NOT NULL DEFAULT 0");

        // Tambahkan kolom baru untuk basis data lama, lalu buang kolom PIN
        // (basis data versi sebelumnya) agar tidak ada sisa data PIN di disk.
        EnsureColumn("students", "birth_date", "TEXT");
        DropColumnIfExists("students", "has_pin");
        DropColumnIfExists("students", "pin_algo");
        DropColumnIfExists("students", "pin_salt");
        DropColumnIfExists("students", "pin_iterations");
        DropColumnIfExists("students", "pin_hash");
        DropColumnIfExists("students", "pin_failed");
        DropColumnIfExists("students", "pin_locked_until");
    }

    private void Execute(string sql)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = sql;
        command.ExecuteNonQuery();
    }

    /// <summary>
    /// Menambahkan kolom yang belum ada (idempoten) agar basis data lama
    /// ikut ter-upgrade tanpa menghapus cache siswa/sesi.
    /// </summary>
    private void EnsureColumn(string table, string column, string definition)
    {
        var exists = false;

        using (var command = _connection.CreateCommand())
        {
            command.CommandText = $"PRAGMA table_info({table})";

            using var reader = command.ExecuteReader();

            while (reader.Read())
            {
                if (string.Equals(reader.GetString(1), column, StringComparison.OrdinalIgnoreCase))
                {
                    exists = true;

                    break;
                }
            }
        }

        if (!exists)
        {
            Execute($"ALTER TABLE {table} ADD COLUMN {column} {definition}");
        }
    }

    /// <summary>
    /// Membuang kolom lama secara idempoten (mis. kolom PIN pada basis data
    /// versi sebelumnya). Kegagalan (SQLite lama tanpa DROP COLUMN) diabaikan.
    /// </summary>
    private void DropColumnIfExists(string table, string column)
    {
        var exists = false;

        using (var command = _connection.CreateCommand())
        {
            command.CommandText = $"PRAGMA table_info({table})";

            using var reader = command.ExecuteReader();

            while (reader.Read())
            {
                if (string.Equals(reader.GetString(1), column, StringComparison.OrdinalIgnoreCase))
                {
                    exists = true;

                    break;
                }
            }
        }

        if (!exists)
        {
            return;
        }

        try
        {
            Execute($"ALTER TABLE {table} DROP COLUMN {column}");
        }
        catch (SqliteException)
        {
            // Kolom tak terpakai dibiarkan pada SQLite yang tidak mendukung DROP COLUMN.
        }
    }

    private static string? Iso(DateTimeOffset? value) => value?.ToUniversalTime().ToString("o");

    private static DateTimeOffset? ParseIso(string? value) =>
        string.IsNullOrEmpty(value)
            ? null
            : DateTimeOffset.Parse(value, CultureInfo.InvariantCulture, DateTimeStyles.RoundtripKind);

    private static string? ToDbDate(DateOnly? value) =>
        value?.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture);

    private static DateOnly? ParseDbDate(string? value)
    {
        if (string.IsNullOrEmpty(value))
        {
            return null;
        }

        if (DateOnly.TryParseExact(value, "yyyy-MM-dd", CultureInfo.InvariantCulture, DateTimeStyles.None, out var exact))
        {
            return exact;
        }

        return DateOnly.TryParse(value, CultureInfo.InvariantCulture, DateTimeStyles.None, out var parsed)
            ? parsed
            : null;
    }

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
                INSERT INTO students (nisn, name, class, birth_date)
                VALUES ($nisn, $name, $class, $birthDate)";
            insert.Parameters.AddWithValue("$nisn", _protector.Encrypt(student.Nisn) ?? string.Empty);
            insert.Parameters.AddWithValue("$name", _protector.Encrypt(student.Name) ?? string.Empty);
            insert.Parameters.AddWithValue("$class", _protector.Encrypt(student.ClassName) ?? string.Empty);
            insert.Parameters.AddWithValue("$birthDate", (object?)_protector.Encrypt(ToDbDate(student.BirthDate)) ?? DBNull.Value);
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
        command.CommandText = "SELECT nisn, name, class, birth_date FROM students";

        using var reader = command.ExecuteReader();

        while (reader.Read())
        {
            // NISN disimpan terenkripsi (nonce acak), jadi pencarian dilakukan
            // dengan mendekripsi baris per baris lalu membandingkan nilai asli.
            var storedNisn = reader.IsDBNull(0) ? null : reader.GetString(0);
            var plainNisn = _protector.Decrypt(storedNisn);

            if (!string.Equals(plainNisn, nisn, StringComparison.Ordinal))
            {
                continue;
            }

            return new CachedStudent
            {
                Nisn = plainNisn ?? string.Empty,
                Name = _protector.Decrypt(reader.IsDBNull(1) ? null : reader.GetString(1)) ?? string.Empty,
                ClassName = _protector.Decrypt(reader.IsDBNull(2) ? null : reader.GetString(2)) ?? string.Empty,
                BirthDate = ParseDbDate(_protector.Decrypt(reader.IsDBNull(3) ? null : reader.GetString(3))),
            };
        }

        return null;
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

    // ---------- Sesi lokal ----------

    public void SaveSession(LocalSessionRecord record)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = @"
            INSERT INTO sessions (
                session_uuid, user_type, nisn, nip_id, subject_id, purpose,
                started_at_client, last_heartbeat_at, ended_at_client,
                feedback, comprehension, close_reason, state,
                screenshot_path, screenshot_state, screenshot_captured_at, created_at
            ) VALUES (
                $uuid, $userType, $nisn, $nip, $subjectId, $purpose,
                $started, $heartbeat, $ended,
                $feedback, $comprehension, $reason, $state,
                $shotPath, $shotState, $shotCaptured, $created
            )
            ON CONFLICT(session_uuid) DO UPDATE SET
                last_heartbeat_at = excluded.last_heartbeat_at,
                ended_at_client = excluded.ended_at_client,
                feedback = excluded.feedback,
                comprehension = excluded.comprehension,
                close_reason = excluded.close_reason,
                state = excluded.state,
                screenshot_path = excluded.screenshot_path,
                screenshot_state = excluded.screenshot_state,
                screenshot_captured_at = excluded.screenshot_captured_at";
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
        command.Parameters.AddWithValue("$shotCaptured", (object?)Iso(record.ScreenshotCapturedAt) ?? DBNull.Value);
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

    /// <summary>
    /// Menambah jumlah percobaan sinkronisasi yang gagal untuk satu sesi
    /// (mengembalikan nilai terbaru) agar antrean tidak dikirim tanpa batas.
    /// </summary>
    public int IncrementSyncAttempts(string sessionUuid)
    {
        using (var update = _connection.CreateCommand())
        {
            update.CommandText = "UPDATE sessions SET sync_failed_attempts = sync_failed_attempts + 1 WHERE session_uuid = $uuid";
            update.Parameters.AddWithValue("$uuid", sessionUuid);
            update.ExecuteNonQuery();
        }

        using var command = _connection.CreateCommand();
        command.CommandText = "SELECT sync_failed_attempts FROM sessions WHERE session_uuid = $uuid";
        command.Parameters.AddWithValue("$uuid", sessionUuid);

        var value = command.ExecuteScalar();

        return value is null or DBNull ? 0 : Convert.ToInt32(value);
    }

    /// <summary>Menandai sesi berhenti dikirim karena ditolak server berulang kali.</summary>
    public void MarkSessionSyncFailed(string sessionUuid)
    {
        using var command = _connection.CreateCommand();
        command.CommandText = "UPDATE sessions SET state = 'failed' WHERE session_uuid = $uuid";
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
            ScreenshotCapturedAt = ParseIso(GetNullable("screenshot_captured_at")),
            SyncFailedAttempts = reader.GetInt32(reader.GetOrdinal("sync_failed_attempts")),
            CreatedAt = ParseIso(GetNullable("created_at")) ?? DateTimeOffset.UtcNow,
        };
    }

    public void Dispose()
    {
        _connection.Dispose();
    }
}
