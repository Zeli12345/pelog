using System.Security.Cryptography;
using System.Text;
using Microsoft.Win32;

namespace PelogKiosk.Core.Security;

/// <summary>
/// Melindungi kolom sensitif siswa (NISN, nama, kelas, tanggal lahir) di SQLite.
///
/// Format nilai tersimpan: "enc:v1:" + base64(nonce(12 byte) | tag(16 byte) | ciphertext).
/// Kunci master 32 byte acak disimpan di tabel kv dan dilindungi DPAPI
/// (<see cref="DataProtectionScope.CurrentUser"/>) dengan entropy = konstanta aplikasi +
/// nama mesin + MachineGuid registry (fallback nama mesin bila registry tidak terbaca).
///
/// Nilai tanpa prefix "enc:v1:" dianggap plaintext dari basis data versi lama dan
/// dikembalikan apa adanya, sehingga upgrade tidak menghilangkan cache siswa.
/// </summary>
public sealed class StudentDataProtector
{
    public const string Prefix = "enc:v1:";

    public const string MasterKeyKvKey = "student_data_master_key";

    private const string DpapiPrefix = "dpapi:";

    private const string PlainPrefix = "plain:";

    private const int KeySize = 32;

    private const int NonceSize = 12;

    private const int TagSize = 16;

    private static readonly byte[] Entropy = BuildEntropy();

    private readonly byte[] _key;

    public StudentDataProtector(Func<string, string?> getKv, Action<string, string> setKv)
    {
        _key = LoadOrCreateKey(getKv, setKv);
    }

    /// <summary>Mengenkripsi nilai; null tetap null agar kolom opsional (mis. birth_date) kosong.</summary>
    public string? Encrypt(string? plaintext)
    {
        if (plaintext is null)
        {
            return null;
        }

        var nonce = RandomNumberGenerator.GetBytes(NonceSize);
        var plainBytes = Encoding.UTF8.GetBytes(plaintext);
        var cipherBytes = new byte[plainBytes.Length];
        var tag = new byte[TagSize];

        using (var aes = new AesGcm(_key, TagSize))
        {
            aes.Encrypt(nonce, plainBytes, cipherBytes, tag);
        }

        var payload = new byte[NonceSize + TagSize + cipherBytes.Length];

        Buffer.BlockCopy(nonce, 0, payload, 0, NonceSize);
        Buffer.BlockCopy(tag, 0, payload, NonceSize, TagSize);
        Buffer.BlockCopy(cipherBytes, 0, payload, NonceSize + TagSize, cipherBytes.Length);

        return Prefix + Convert.ToBase64String(payload);
    }

    /// <summary>
    /// Mendekripsi nilai. Nilai tanpa prefix "enc:v1:" dikembalikan apa adanya
    /// (kompatibel dengan basis data lama yang masih plaintext).
    /// </summary>
    public string? Decrypt(string? stored)
    {
        if (stored is null || !stored.StartsWith(Prefix, StringComparison.Ordinal))
        {
            return stored;
        }

        var payload = Convert.FromBase64String(stored[Prefix.Length..]);

        if (payload.Length < NonceSize + TagSize)
        {
            throw new InvalidOperationException("Data siswa terenkripsi tidak valid.");
        }

        var nonce = payload.AsSpan(0, NonceSize);
        var tag = payload.AsSpan(NonceSize, TagSize);
        var cipherBytes = payload.AsSpan(NonceSize + TagSize);
        var plainBytes = new byte[cipherBytes.Length];

        using (var aes = new AesGcm(_key, TagSize))
        {
            aes.Decrypt(nonce, cipherBytes, tag, plainBytes);
        }

        return Encoding.UTF8.GetString(plainBytes);
    }

    private static byte[] LoadOrCreateKey(Func<string, string?> getKv, Action<string, string> setKv)
    {
        var stored = getKv(MasterKeyKvKey);

        if (!string.IsNullOrEmpty(stored))
        {
            var existing = UnprotectKey(stored);

            if (existing is { Length: KeySize })
            {
                return existing;
            }
        }

        var created = RandomNumberGenerator.GetBytes(KeySize);
        setKv(MasterKeyKvKey, ProtectKey(created));

        return created;
    }

    private static string ProtectKey(byte[] key)
    {
        if (OperatingSystem.IsWindows())
        {
            var protectedBytes = ProtectedData.Protect(key, Entropy, DataProtectionScope.CurrentUser);

            return DpapiPrefix + Convert.ToBase64String(protectedBytes);
        }

        // Fallback pengembangan non-Windows; kiosk produksi selalu Windows.
        return PlainPrefix + Convert.ToBase64String(key);
    }

    private static byte[]? UnprotectKey(string stored)
    {
        try
        {
            if (stored.StartsWith(DpapiPrefix, StringComparison.Ordinal))
            {
                if (!OperatingSystem.IsWindows())
                {
                    return null;
                }

                var bytes = Convert.FromBase64String(stored[DpapiPrefix.Length..]);

                return ProtectedData.Unprotect(bytes, Entropy, DataProtectionScope.CurrentUser);
            }

            if (stored.StartsWith(PlainPrefix, StringComparison.Ordinal))
            {
                return Convert.FromBase64String(stored[PlainPrefix.Length..]);
            }
        }
        catch (Exception)
        {
            // Kunci rusak/tidak dapat dibuka -> dibuat ulang (data lama tidak terbaca).
            return null;
        }

        return null;
    }

    private static byte[] BuildEntropy()
    {
        const string appConstant = "PELOG-student-data-v1";

        var machineName = Environment.MachineName;
        var machineGuid = TryGetMachineGuid();

        var value = string.IsNullOrEmpty(machineGuid)
            ? $"{appConstant}|{machineName}"
            : $"{appConstant}|{machineName}|{machineGuid}";

        return Encoding.UTF8.GetBytes(value);
    }

    private static string? TryGetMachineGuid()
    {
        if (!OperatingSystem.IsWindows())
        {
            return null;
        }

        try
        {
            using var key = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Cryptography");

            return key?.GetValue("MachineGuid") as string;
        }
        catch (Exception)
        {
            // Fallback: entropy hanya memakai nama mesin.
            return null;
        }
    }
}
