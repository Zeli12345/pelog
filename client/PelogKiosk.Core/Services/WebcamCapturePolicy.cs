using Microsoft.Win32;

namespace PelogKiosk.Core.Services;

/// <summary>
/// Menentukan kapan foto webcam diambil: begitu kamera terdeteksi aktif,
/// lalu berjarak minimal sesuai interval. Capture pertama selalu langsung.
/// </summary>
public static class WebcamCapturePolicy
{
    public static bool ShouldCapture(
        DateTimeOffset? lastCapturedAt,
        bool hasPendingCapture,
        DateTimeOffset now,
        TimeSpan interval)
    {
        if (hasPendingCapture)
        {
            return false;
        }

        if (lastCapturedAt is null)
        {
            return true;
        }

        if (now < lastCapturedAt.Value)
        {
            return false;
        }

        return now - lastCapturedAt.Value >= interval;
    }
}

/// <summary>
/// Mendeteksi apakah kamera sedang dipakai aplikasi lain lewat Windows
/// CapabilityAccessManager (Windows 10 1903+). Bila registry tidak tersedia,
/// pemanggil boleh mencoba membuka kamera secara langsung.
/// </summary>
public static class WebcamActivityDetector
{
    private const string ConsentRoot = @"Software\Microsoft\Windows\CurrentVersion\CapabilityAccessManager\ConsentStore\webcam";

    public static bool IsCameraInUse()
    {
        try
        {
            using var root = Registry.CurrentUser.OpenSubKey(ConsentRoot);

            if (root is null)
            {
                return false;
            }

            foreach (var childName in root.GetSubKeyNames())
            {
                using var child = root.OpenSubKey(childName);

                if (child is null)
                {
                    continue;
                }

                if (string.Equals(childName, "NonPackaged", StringComparison.OrdinalIgnoreCase))
                {
                    foreach (var appName in child.GetSubKeyNames())
                    {
                        using var app = child.OpenSubKey(appName);

                        if (app is not null && IsActiveApp(appName) && HasActiveSession(app))
                        {
                            return true;
                        }
                    }

                    continue;
                }

                if (!IsActiveApp(childName) && HasActiveSession(child))
                {
                    return true;
                }
            }
        }
        catch (Exception)
        {
            // Registry tidak tersedia / akses ditolak: pemanggil memakai fallback.
        }

        return false;
    }

    private static bool HasActiveSession(RegistryKey key)
    {
        var start = ReadLong(key, "LastUsedTimeStart");
        var stop = ReadLong(key, "LastUsedTimeStop");

        // Stop = 0 dan start > 0 berarti kamera masih aktif.
        return start > 0 && stop == 0;
    }

    private static bool IsActiveApp(string keyName)
    {
        return !keyName.Contains("PelogKiosk", StringComparison.OrdinalIgnoreCase);
    }

    private static long ReadLong(RegistryKey key, string name)
    {
        var value = key.GetValue(name);

        return value switch
        {
            long number => number,
            int number => number,
            byte[] bytes when bytes.Length >= 8 => BitConverter.ToInt64(bytes, 0),
            _ => 0,
        };
    }
}
