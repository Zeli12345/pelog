namespace BalilogKiosk.Core.Api;

/// <summary>
/// Deteksi perangkat yang DIHAPUS dari dashboard.
/// Server membalas HTTP 410 dengan kode error "device_revoked"; deaktivasi
/// biasa tetap 401 "device_token_invalid" dan tidak memicu wipe.
/// </summary>
public static class DeviceRevocation
{
    public const string ErrorCode = "device_revoked";

    public static bool IsRevoked(int httpStatus, string? errorCode) =>
        httpStatus == 410 && string.Equals(errorCode, ErrorCode, StringComparison.Ordinal);
}
