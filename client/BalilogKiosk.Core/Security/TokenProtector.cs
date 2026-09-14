using System.Runtime.Versioning;
using System.Security.Cryptography;
using System.Text;

namespace BalilogKiosk.Core.Security;

/// <summary>
/// Menyimpan token perangkat dengan proteksi DPAPI (per akun Windows).
/// Jika DPAPI tidak tersedia, fallback ke base64 (hanya untuk pengembangan).
/// </summary>
[SupportedOSPlatform("windows")]
public static class TokenProtector
{
    private static readonly byte[] Entropy = Encoding.UTF8.GetBytes("BALI-LOG-token-v1");

    public static string Protect(string plaintext)
    {
        try
        {
            var bytes = Encoding.UTF8.GetBytes(plaintext);
            var protectedBytes = ProtectedData.Protect(bytes, Entropy, DataProtectionScope.CurrentUser);

            return "dpapi:" + Convert.ToBase64String(protectedBytes);
        }
        catch (PlatformNotSupportedException)
        {
            return "plain:" + Convert.ToBase64String(Encoding.UTF8.GetBytes(plaintext));
        }
    }

    public static string? Unprotect(string stored)
    {
        try
        {
            if (stored.StartsWith("dpapi:", StringComparison.Ordinal))
            {
                var bytes = Convert.FromBase64String(stored["dpapi:".Length..]);
                var plain = ProtectedData.Unprotect(bytes, Entropy, DataProtectionScope.CurrentUser);

                return Encoding.UTF8.GetString(plain);
            }

            if (stored.StartsWith("plain:", StringComparison.Ordinal))
            {
                return Encoding.UTF8.GetString(Convert.FromBase64String(stored["plain:".Length..]));
            }
        }
        catch (Exception)
        {
            return null;
        }

        return null;
    }
}
