using System.Runtime.InteropServices;

namespace BalilogKiosk.App.Services;

/// <summary>
/// Mematikan komputer dari dalam aplikasi (dipakai setelah refleksi shutdown
/// tersimpan). Membutuhkan hak SE_SHUTDOWN_NAME; bila hak ditolak, pemanggil
/// harus menampilkan instruksi manual kepada siswa.
/// </summary>
public static class ShutdownController
{
    private const uint TokenAdjustPrivileges = 0x0020;
    private const uint TokenQuery = 0x0008;
    private const uint SePrivilegeEnabled = 0x0002;
    private const uint EwxPowerOff = 0x0008;
    private const uint EwxForceIfHung = 0x0010;

    [DllImport("user32.dll", SetLastError = true)]
    private static extern bool ExitWindowsEx(uint uFlags, uint dwReason);

    [DllImport("advapi32.dll", SetLastError = true)]
    private static extern bool OpenProcessToken(IntPtr processHandle, uint desiredAccess, out IntPtr tokenHandle);

    [DllImport("advapi32.dll", SetLastError = true)]
    private static extern bool LookupPrivilegeValue(string? systemName, string name, out Luid luid);

    [DllImport("advapi32.dll", SetLastError = true)]
    private static extern bool AdjustTokenPrivileges(IntPtr tokenHandle, bool disableAllPrivileges, ref TokenPrivileges newState, uint bufferLength, IntPtr previousState, IntPtr returnLength);

    [DllImport("kernel32.dll")]
    private static extern IntPtr GetCurrentProcess();

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool CloseHandle(IntPtr handle);

    [StructLayout(LayoutKind.Sequential)]
    private struct Luid
    {
        public uint LowPart;
        public int HighPart;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct TokenPrivileges
    {
        public uint PrivilegeCount;
        public Luid Luid;
        public uint Attributes;
    }

    /// <summary>
    /// Mematikan komputer (power off) beserta semua aplikasi yang menggantung.
    /// Mengembalikan false bila hak shutdown tidak tersedia.
    /// </summary>
    public static bool PowerOff()
    {
        try
        {
            if (!EnableShutdownPrivilege())
            {
                return false;
            }

            return ExitWindowsEx(EwxPowerOff | EwxForceIfHung, 0);
        }
        catch (Exception)
        {
            return false;
        }
    }

    private static bool EnableShutdownPrivilege()
    {
        if (!OpenProcessToken(GetCurrentProcess(), TokenAdjustPrivileges | TokenQuery, out var token))
        {
            return false;
        }

        try
        {
            if (!LookupPrivilegeValue(null, "SeShutdownPrivilege", out var luid))
            {
                return false;
            }

            var privileges = new TokenPrivileges
            {
                PrivilegeCount = 1,
                Luid = luid,
                Attributes = SePrivilegeEnabled,
            };

            if (!AdjustTokenPrivileges(token, false, ref privileges, 0, IntPtr.Zero, IntPtr.Zero))
            {
                return false;
            }

            // AdjustTokenPrivileges sukses meski hak tidak diberikan; cek error terakhir.
            return Marshal.GetLastWin32Error() == 0;
        }
        finally
        {
            CloseHandle(token);
        }
    }
}
