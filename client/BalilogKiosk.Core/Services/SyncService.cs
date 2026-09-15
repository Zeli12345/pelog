using System.Text.Json;
using BalilogKiosk.Core.Api;
using BalilogKiosk.Core.Data;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Time;

namespace BalilogKiosk.Core.Services;

/// <summary>
/// Sinkronisasi latar belakang: segarkan cache master data,
/// kirim sesi tertunda (batch), dan unggah screenshot yang masih tertahan.
/// </summary>
public sealed class SyncService
{
    private static readonly JsonSerializerOptions JsonOptions = new(JsonSerializerDefaults.Web)
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        PropertyNameCaseInsensitive = true,
    };

    private readonly LocalStore _store;

    private readonly BalilogApiClient _api;

    private readonly ServerClock _clock;

    public SyncService(LocalStore store, BalilogApiClient api, ServerClock clock)
    {
        _store = store;
        _api = api;
        _clock = clock;
    }

    public string? LastError { get; private set; }

    public ClientConfig LoadCachedConfig()
    {
        var json = _store.GetKv("client_config");

        if (string.IsNullOrEmpty(json))
        {
            return new ClientConfig();
        }

        try
        {
            return JsonSerializer.Deserialize<ClientConfig>(json, JsonOptions) ?? new ClientConfig();
        }
        catch (JsonException)
        {
            return new ClientConfig();
        }
    }

    public async Task<bool> RefreshBootstrapAsync(CancellationToken cancellationToken = default)
    {
        var result = await _api.BootstrapAsync(cancellationToken);

        if (!result.Ok || result.Data is null)
        {
            LastError = result.ErrorMessage;

            return false;
        }

        _store.ReplaceStudents(result.Data.Students);
        _store.ReplaceStaff(result.Data.Staff);
        _store.ReplaceSubjects(result.Data.Subjects);
        _store.SetKv("client_config", JsonSerializer.Serialize(result.Data.Config, JsonOptions));

        if (result.Data.Device is { } device)
        {
            _store.SetKv("device_maintenance", device.Maintenance ? "1" : "0");

            if (!string.IsNullOrWhiteSpace(device.Label))
            {
                _store.SetKv("device_label", device.Label);
            }
        }

        _store.SetKv("bootstrap_at", _clock.Now.ToString("o"));
        LastError = null;

        return true;
    }

    public async Task<int> PushSessionsAsync(CancellationToken cancellationToken = default)
    {
        var pending = _store.GetPendingSessions();

        if (pending.Count == 0)
        {
            return 0;
        }

        var pushed = 0;

        foreach (var batch in Chunk(pending, 100))
        {
            var items = batch.Select(ToSyncItem).ToList();
            var result = await _api.SyncSessionsAsync(items, cancellationToken);

            if (!result.Ok || result.Data is null)
            {
                LastError = result.ErrorMessage;

                break;
            }

            var statusByUuid = result.Data.Results
                .Where(item => !string.IsNullOrEmpty(item.SessionUuid))
                .GroupBy(item => item.SessionUuid!)
                .ToDictionary(group => group.Key, group => group.First().Status);

            foreach (var record in batch)
            {
                if (statusByUuid.TryGetValue(record.SessionUuid, out var status) &&
                    (status == "created" || status == "skipped"))
                {
                    _store.MarkSessionSynced(record.SessionUuid);
                    pushed++;
                }
            }
        }

        return pushed;
    }

    public async Task<int> PushScreenshotsAsync(CancellationToken cancellationToken = default)
    {
        var pending = _store.GetPendingScreenshots();

        if (pending.Count == 0)
        {
            return 0;
        }

        var pushed = 0;

        foreach (var record in pending)
        {
            if (string.IsNullOrEmpty(record.ScreenshotPath) || !File.Exists(record.ScreenshotPath))
            {
                // Berkas hilang: tandai selesai agar tidak menghambat antrean.
                _store.MarkScreenshotSynced(record.SessionUuid);

                continue;
            }

            var result = await _api.UploadScreenshotAsync(
                record.SessionUuid,
                record.ScreenshotPath,
                Guid.NewGuid().ToString(),
                record.LastHeartbeatAt ?? _clock.Now,
                cancellationToken);

            if (!result.Ok)
            {
                LastError = result.ErrorMessage;

                break;
            }

            _store.MarkScreenshotSynced(record.SessionUuid);
            pushed++;
        }

        return pushed;
    }

    private static SyncSessionItem ToSyncItem(LocalSessionRecord record)
    {
        return new SyncSessionItem
        {
            SessionUuid = record.SessionUuid,
            UserType = record.UserType,
            Nisn = record.Nisn,
            NipId = record.NipId,
            SubjectId = record.SubjectId,
            UsagePurpose = record.UsagePurpose,
            StartedAtClient = record.StartedAtClient,
            EndedAtClient = record.EndedAtClient,
            CloseReason = record.CloseReason,
            StudentFeedback = record.Feedback,
            ComprehensionLevel = record.Comprehension,
        };
    }

    private static IEnumerable<List<T>> Chunk<T>(List<T> source, int size)
    {
        for (var index = 0; index < source.Count; index += size)
        {
            yield return source.GetRange(index, Math.Min(size, source.Count - index));
        }
    }
}
