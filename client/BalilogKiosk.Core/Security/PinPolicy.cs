namespace BalilogKiosk.Core.Security;

/// <summary>
/// Kebijakan PIN yang sama dengan sisi server (PinPolicy.php).
/// </summary>
public static class PinPolicy
{
    public static bool IsWeak(string pin)
    {
        if (string.IsNullOrEmpty(pin) || !pin.All(char.IsAsciiDigit))
        {
            return true;
        }

        // Semua digit sama: 0000, 1111, ...
        if (pin.All(c => c == pin[0]))
        {
            return true;
        }

        // Berurutan naik/turun: 1234, 4321, 0123, ...
        var ascending = true;
        var descending = true;

        for (var i = 1; i < pin.Length; i++)
        {
            var previous = pin[i - 1] - '0';
            var current = pin[i] - '0';

            if (current != (previous + 1) % 10)
            {
                ascending = false;
            }

            if (current != (previous + 9) % 10)
            {
                descending = false;
            }
        }

        return ascending || descending;
    }
}
