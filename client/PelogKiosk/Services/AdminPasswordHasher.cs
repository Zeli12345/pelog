using System.Security.Cryptography;

namespace PelogKiosk.App.Services;

/// <summary>
/// Hashing password admin kiosk. Format tersimpan kompatibel dengan basis data
/// lama: pbkdf2-sha256$iterations$salt$hash (salt base64 16 byte, hash base64
/// 32 byte) sehingga password yang sudah diatur sebelumnya tetap valid.
/// </summary>
internal static class AdminPasswordHasher
{
    public const string Algo = "pbkdf2-sha256";

    public const int DefaultIterations = 100_000;

    public const int SaltBytes = 16;

    public const int KeyBytes = 32;

    public static (string Algo, string Salt, int Iterations, string Hash) Make(
        string password,
        byte[]? salt = null,
        int? iterations = null)
    {
        var saltBytes = salt ?? RandomNumberGenerator.GetBytes(SaltBytes);
        var iterationCount = iterations ?? DefaultIterations;

        var hash = Rfc2898DeriveBytes.Pbkdf2(
            password,
            saltBytes,
            iterationCount,
            HashAlgorithmName.SHA256,
            KeyBytes);

        return (Algo, Convert.ToBase64String(saltBytes), iterationCount, Convert.ToBase64String(hash));
    }

    public static bool Verify(string password, string saltBase64, int iterations, string hashBase64)
    {
        try
        {
            var salt = Convert.FromBase64String(saltBase64);
            var expected = Convert.FromBase64String(hashBase64);

            if (iterations <= 0 || expected.Length == 0)
            {
                return false;
            }

            var actual = Rfc2898DeriveBytes.Pbkdf2(
                password,
                salt,
                iterations,
                HashAlgorithmName.SHA256,
                expected.Length);

            return CryptographicOperations.FixedTimeEquals(expected, actual);
        }
        catch (FormatException)
        {
            return false;
        }
    }
}
