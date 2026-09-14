using System.Security.Cryptography;

namespace BalilogKiosk.Core.Security;

/// <summary>
/// Hashing PIN lintas-platform (kompatibel dengan PHP hash_pbkdf2 pada server).
/// Algoritma: PBKDF2-HMAC-SHA256, output base64 (32 byte), salt base64 (16 byte).
/// </summary>
public static class PinHasher
{
    public const string Algo = "pbkdf2-sha256";

    public const int DefaultIterations = 100_000;

    public const int SaltBytes = 16;

    public const int KeyBytes = 32;

    public static (string Algo, string Salt, int Iterations, string Hash) Make(
        string pin,
        byte[]? salt = null,
        int? iterations = null)
    {
        var saltBytes = salt ?? RandomNumberGenerator.GetBytes(SaltBytes);
        var iterationCount = iterations ?? DefaultIterations;

        var hash = Rfc2898DeriveBytes.Pbkdf2(
            pin,
            saltBytes,
            iterationCount,
            HashAlgorithmName.SHA256,
            KeyBytes);

        return (Algo, Convert.ToBase64String(saltBytes), iterationCount, Convert.ToBase64String(hash));
    }

    public static bool Verify(string pin, string saltBase64, int iterations, string hashBase64)
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
                pin,
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
