using System.Text.Json.Serialization;

namespace PelogKiosk.Core.Models;

public sealed class ApiEnvelope<T>
{
    public bool Ok { get; set; }

    public T? Data { get; set; }

    public ApiErrorPayload? Error { get; set; }

    public DateTimeOffset? ServerTime { get; set; }
}

public sealed class ApiErrorPayload
{
    public string? Code { get; set; }

    public string? Message { get; set; }
}

public sealed class ClientConfig
{
    public string SchoolName { get; set; } = "SMK Negeri 1 Mas Ubud";

    public string SchoolMotto { get; set; } = "Kriya Kencana Raksa";

    public bool ScreenshotEnabled { get; set; } = true;

    public string ImageFormat { get; set; } = "webp_fallback_jpeg";

    public int WebpQuality { get; set; } = 70;

    public int JpegQuality { get; set; } = 60;

    public int MaxWidth { get; set; } = 1280;

    public int ScreenshotMinute { get; set; } = 30;

    public int StaleSessionMinutes { get; set; } = 15;

    public int BootstrapRefreshMinutes { get; set; } = 15;

    public int DeviceOnlineWindowSeconds { get; set; } = 300;
}

public sealed class EnrollRequest
{
    public string EnrollmentCode { get; set; } = string.Empty;

    public string DeviceUuid { get; set; } = string.Empty;

    public string Hostname { get; set; } = string.Empty;

    public string? Label { get; set; }

    public string? Location { get; set; }

    public List<string>? MacList { get; set; }

    public string DeviceType { get; set; } = "laptop";

    public string? AgentVersion { get; set; } = AppInfo.Version;

    public string? WindowsVersion { get; set; }

    public int? StorageTotalGb { get; set; }

    public int? StorageUsedGb { get; set; }
}

public sealed class EnrollData
{
    public DeviceProfile? Device { get; set; }

    public string DeviceToken { get; set; } = string.Empty;

    public ClientConfig Config { get; set; } = new();
}

public sealed class DeviceProfile
{
    public string Uuid { get; set; } = string.Empty;

    public string Hostname { get; set; } = string.Empty;

    public string? Label { get; set; }
}

public sealed class BootstrapData
{
    public List<CachedStudent> Students { get; set; } = [];

    public List<CachedStaff> Staff { get; set; } = [];

    public List<CachedSubject> Subjects { get; set; } = [];

    public DeviceInfo? Device { get; set; }

    public ClientConfig Config { get; set; } = new();
}

public sealed class DeviceInfo
{
    public string Hostname { get; set; } = string.Empty;

    public string? Label { get; set; }

    public bool Maintenance { get; set; }
}

public sealed class CachedStudent
{
    public string Nisn { get; set; } = string.Empty;

    public string Name { get; set; } = string.Empty;

    [JsonPropertyName("class")]
    public string ClassName { get; set; } = string.Empty;

    public DateOnly? BirthDate { get; set; }

    public DateTimeOffset? UpdatedAt { get; set; }
}

public sealed class CachedStaff
{
    public string NipId { get; set; } = string.Empty;

    public string Name { get; set; } = string.Empty;

    public string Role { get; set; } = "teacher";
}

public sealed class CachedSubject
{
    public long Id { get; set; }

    public string Code { get; set; } = string.Empty;

    public string Name { get; set; } = string.Empty;
}

public sealed class StartSessionRequest
{
    public string SessionUuid { get; set; } = string.Empty;

    public string UserType { get; set; } = "student";

    public string? Nisn { get; set; }

    public string? NipId { get; set; }

    public long? SubjectId { get; set; }

    public string UsagePurpose { get; set; } = string.Empty;

    public DateTimeOffset? StartedAtClient { get; set; }

    public int? StorageTotalGb { get; set; }

    public int? StorageUsedGb { get; set; }

    public int? CpuUsagePercent { get; set; }

    public int? RamUsagePercent { get; set; }

    public int? RamTotalGb { get; set; }

    public string? GpuName { get; set; }
}

public sealed class SessionDto
{
    public string SessionUuid { get; set; } = string.Empty;

    public string UserType { get; set; } = "student";

    public bool Active { get; set; }

    public DateTimeOffset? StartedAtClient { get; set; }

    public DateTimeOffset? StartedAtServer { get; set; }

    public DateTimeOffset? ClosedAt { get; set; }

    public string? CloseReason { get; set; }

    public int? DurationMinutes { get; set; }
}

public sealed class HeartbeatData
{
    public bool Active { get; set; }

    public DateTimeOffset? ClosedAt { get; set; }

    public string? CloseReason { get; set; }

    public bool ScreenshotRequested { get; set; }
}

/// <summary>Metrik sistem yang dilaporkan kiosk (CPU %, RAM %, total RAM GB, GPU).</summary>
public sealed record SystemMetricsPayload(int? CpuPercent, int? RamPercent, int? RamTotalGb, string? GpuName);

public sealed class EndSessionRequest
{
    public string SessionUuid { get; set; } = string.Empty;

    public string? CloseReason { get; set; }

    public DateTimeOffset? EndedAtClient { get; set; }
}

public sealed class ScreenshotData
{
    public string ScreenshotUuid { get; set; } = string.Empty;

    public string Format { get; set; } = string.Empty;

    public long SizeBytes { get; set; }

    public bool ThumbAvailable { get; set; }
}

public sealed class SyncSessionItem
{
    public string SessionUuid { get; set; } = string.Empty;

    public string UserType { get; set; } = "student";

    public string? Nisn { get; set; }

    public string? NipId { get; set; }

    public long? SubjectId { get; set; }

    public string UsagePurpose { get; set; } = string.Empty;

    public DateTimeOffset? StartedAtClient { get; set; }

    public DateTimeOffset? EndedAtClient { get; set; }

    public string? CloseReason { get; set; }
}

public sealed class SyncSessionResult
{
    public string? SessionUuid { get; set; }

    public string Status { get; set; } = "unknown";

    public string? Message { get; set; }
}

public sealed class SyncResults
{
    public List<SyncSessionResult> Results { get; set; } = [];
}

public sealed class AppReleaseInfo
{
    public bool Available { get; set; }

    public string? Version { get; set; }

    public string? Sha256 { get; set; }

    public long SizeBytes { get; set; }

    public bool Mandatory { get; set; }

    public string? Notes { get; set; }

    public string? Url { get; set; }
}
