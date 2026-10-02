using PelogKiosk.Core.Api;
using PelogKiosk.Core.Models;
using Xunit;

namespace PelogKiosk.Tests;

/// <summary>
/// Logika deteksi perangkat yang DIHAPUS dari dashboard â€” inti dari
/// SelfWipeService.IsRevoked: hanya HTTP 410 + kode "device_revoked".
/// Deaktivasi biasa (401 device_token_invalid), timeout, dan gangguan
/// jaringan tidak boleh memicu wipe.
/// </summary>
public class DeviceRevocationTests
{
    [Theory]
    [InlineData(410, "device_revoked", true)]
    [InlineData(410, "device_token_invalid", false)]
    [InlineData(410, "DEVICE_REVOKED", false)]
    [InlineData(410, null, false)]
    [InlineData(401, "device_token_invalid", false)]
    [InlineData(401, "device_revoked", false)]
    [InlineData(200, "device_revoked", false)]
    [InlineData(0, "device_revoked", false)]
    [InlineData(0, null, false)]
    public void IsRevoked_Only_For_Http410_And_Exact_Code(int status, string? code, bool expected)
    {
        Assert.Equal(expected, DeviceRevocation.IsRevoked(status, code));
    }

    [Fact]
    public void IsRevoked_Matches_ApiResult_From_Failed_Request()
    {
        var revoked = ApiResult<AppReleaseInfo>.Fail("device_revoked", "Perangkat dihapus.", 410);
        var deactivated = ApiResult<AppReleaseInfo>.Fail("device_token_invalid", "Token ditolak.", 401);
        var timeout = ApiResult<AppReleaseInfo>.Fail("timeout", "Koneksi timeout.");
        var network = ApiResult<AppReleaseInfo>.Fail("network", "Tidak dapat terhubung.");

        Assert.True(DeviceRevocation.IsRevoked(revoked.HttpStatus, revoked.ErrorCode));
        Assert.False(DeviceRevocation.IsRevoked(deactivated.HttpStatus, deactivated.ErrorCode));
        Assert.False(DeviceRevocation.IsRevoked(timeout.HttpStatus, timeout.ErrorCode));
        Assert.False(DeviceRevocation.IsRevoked(network.HttpStatus, network.ErrorCode));
    }
}
