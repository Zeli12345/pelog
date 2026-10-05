using System.Runtime.InteropServices;

namespace PelogKiosk.Core.Services;

/// <summary>
/// Waktu idle (tanpa input mouse/keyboard) pada sesi pengguna saat ini.
/// Dipakai untuk mematikan laptop otomatis bila tidak terpakai.
/// </summary>
public static class IdleDetector
{
    [StructLayout(LayoutKind.Sequential)]
    private struct LastInputInfo
    {
        public uint Size;
        public uint Time;
    }

    [DllImport("user32.dll")]
    private static extern bool GetLastInputInfo(ref LastInputInfo info);

    /// <summary>Lama waktu sejak input terakhir. Kegagalan mengembalikan TimeSpan.Zero (dianggap aktif).</summary>
    public static TimeSpan IdleTime()
    {
        try
        {
            var info = new LastInputInfo { Size = (uint)Marshal.SizeOf<LastInputInfo>() };

            if (!GetLastInputInfo(ref info))
            {
                return TimeSpan.Zero;
            }

            var idleMillis = unchecked((uint)Environment.TickCount - info.Time);

            return TimeSpan.FromMilliseconds(idleMillis);
        }
        catch (Exception)
        {
            return TimeSpan.Zero;
        }
    }
}
