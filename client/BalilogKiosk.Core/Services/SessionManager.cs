using BalilogKiosk.Core.Api;
using BalilogKiosk.Core.Data;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Time;

namespace BalilogKiosk.Core.Services;

public enum RecoveryAction
{
    None,
    Resume,
    ClosedAsRecovery,
}

public sealed record RecoveryResult(RecoveryAction Action, LocalSessionRecord? Record);

/// <summary>
/// Pengelola siklus hidup sesi (lokal-first):
/// mulai -> heartbeat -> selesai, dengan pemulihan sesi menggantung saat aplikasi dibuka kembali.
/// </summary>
public sealed class SessionManager
{
    public static readonly TimeSpan ResumeWindow = TimeSpan.FromMinutes(10);

    private readonly LocalStore _store;

    private readonly BalilogApiClient _api;

    private readonly ServerClock _clock;

    public SessionManager(LocalStore store, BalilogApiClient api, ServerClock clock)
    {
        _store = store;
        _api = api;
        _clock = clock;
    }

    public LocalSessionRecord BeginStudent(string nisn, long? subjectId, string purpose)
    {
        var record = new LocalSessionRecord
        {
            SessionUuid = Guid.NewGuid().ToString(),
            UserType = "student",
            Nisn = nisn,
            SubjectId = subjectId,
            UsagePurpose = purpose,
            StartedAtClient = _clock.Now,
            LastHeartbeatAt = _clock.Now,
            State = "open",
        };

        _store.SaveSession(record);

        return record;
    }

    public LocalSessionRecord BeginStaff(string nipId, string purpose)
    {
        var record = new LocalSessionRecord
        {
            SessionUuid = Guid.NewGuid().ToString(),
            UserType = "staff",
            NipId = nipId,
            UsagePurpose = purpose,
            StartedAtClient = _clock.Now,
            LastHeartbeatAt = _clock.Now,
            State = "open",
        };

        _store.SaveSession(record);

        return record;
    }

    public async Task<ApiResult<SessionDto>?> TryRemoteStartAsync(LocalSessionRecord record, CancellationToken cancellationToken = default)
    {
        try
        {
            var (totalGb, usedGb) = GetSystemStorage();

            var request = new StartSessionRequest
            {
                SessionUuid = record.SessionUuid,
                UserType = record.UserType,
                Nisn = record.Nisn,
                NipId = record.NipId,
                SubjectId = record.SubjectId,
                UsagePurpose = record.UsagePurpose,
                StartedAtClient = record.StartedAtClient,
                StorageTotalGb = totalGb,
                StorageUsedGb = usedGb,
            };

            return await _api.StartSessionAsync(request, cancellationToken);
        }
        catch (Exception)
        {
            return null;
        }
    }

    /// <summary>
    /// Kapasitas drive sistem dalam GB (total, terpakai). Kegagalan apa pun
    /// menghasilkan null agar pembuatan sesi tidak terganggu.
    /// </summary>
    private static (int? TotalGb, int? UsedGb) GetSystemStorage()
    {
        try
        {
            var systemRoot = Path.GetPathRoot(Environment.SystemDirectory);
            var drive = new System.IO.DriveInfo(systemRoot ?? string.Empty);

            if (!drive.IsReady)
            {
                return (null, null);
            }

            const double bytesPerGb = 1024d * 1024d * 1024d;

            return (
                (int)(drive.TotalSize / bytesPerGb),
                (int)((drive.TotalSize - drive.TotalFreeSpace) / bytesPerGb));
        }
        catch (Exception)
        {
            return (null, null);
        }
    }

    public void Heartbeat(LocalSessionRecord record)
    {
        record.LastHeartbeatAt = _clock.Now;
        _store.SaveSession(record);
    }

    public Task<ApiResult<HeartbeatData>> TryRemoteHeartbeatAsync(LocalSessionRecord record, CancellationToken cancellationToken = default)
    {
        return _api.HeartbeatAsync(record.SessionUuid, cancellationToken);
    }

    public LocalSessionRecord Close(LocalSessionRecord record, string? feedback, string? comprehension, string reason = "normal")
    {
        record.EndedAtClient = _clock.Now;
        record.Feedback = feedback;
        record.Comprehension = comprehension;
        record.CloseReason = reason;
        record.State = "closed";
        _store.SaveSession(record);

        return record;
    }

    public async Task TryRemoteEndAsync(LocalSessionRecord record, CancellationToken cancellationToken = default)
    {
        await _api.EndSessionAsync(
            new EndSessionRequest
            {
                SessionUuid = record.SessionUuid,
                CloseReason = record.CloseReason,
                EndedAtClient = record.EndedAtClient,
            },
            cancellationToken);
    }

    public void AttachScreenshot(LocalSessionRecord record, string filePath, DateTimeOffset? capturedAt = null)
    {
        record.ScreenshotPath = filePath;
        record.ScreenshotState = "pending";
        record.ScreenshotCapturedAt = capturedAt ?? _clock.Now;
        _store.SaveSession(record);
    }

    /// <summary>
    /// Dipanggil saat aplikasi mulai: mendeteksi sesi yang masih terbuka.
    /// Jika heartbeat terakhir masih baru -> lanjutkan; jika sudah lama -> tutup sebagai recovery.
    /// </summary>
    public RecoveryResult RecoverOnStartup()
    {
        var shutdownPending = _store.GetKv("shutdown_pending");
        var openSessions = _store.GetOpenSessions();

        if (openSessions.Count == 0)
        {
            ClearShutdownPending(shutdownPending);

            return new RecoveryResult(RecoveryAction.None, null);
        }

        var session = openSessions[^1];
        var lastSeen = session.LastHeartbeatAt ?? session.StartedAtClient ?? session.CreatedAt;

        // Shutdown/restart sempat ditahan untuk meminta refleksi, tetapi mesin
        // tetap mati (mis. siswa memilih "Shut down anyway" atau tombol power):
        // tutup sesi sebagai "shutdown", bukan "recovery".
        if (!string.IsNullOrEmpty(shutdownPending) && shutdownPending == session.SessionUuid)
        {
            session.EndedAtClient = lastSeen;
            session.CloseReason = "shutdown";
            session.State = "closed";
            _store.SaveSession(session);
            _store.SetKv("shutdown_pending", "");

            return new RecoveryResult(RecoveryAction.ClosedAsRecovery, session);
        }

        ClearShutdownPending(shutdownPending);

        if (_clock.Now - lastSeen <= ResumeWindow)
        {
            return new RecoveryResult(RecoveryAction.Resume, session);
        }

        session.EndedAtClient = lastSeen;
        session.CloseReason = "recovery";
        session.State = "closed";
        _store.SaveSession(session);

        return new RecoveryResult(RecoveryAction.ClosedAsRecovery, session);
    }

    private void ClearShutdownPending(string? pending)
    {
        if (!string.IsNullOrEmpty(pending))
        {
            _store.SetKv("shutdown_pending", "");
        }
    }
}
