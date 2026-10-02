using System.Runtime.InteropServices;

namespace PelogKiosk.App.Services;

/// <summary>
/// Memblokir shortcut keluar dari kiosk (Alt+Tab, Alt+Esc, tombol Windows, Ctrl+Esc/Ctrl+Shift+Esc)
/// dengan low-level keyboard hook untuk sesi pengguna saat ini.
/// Shortcut admin (Ctrl+Alt+Shift+B) tetap diizinkan.
/// </summary>
public sealed class KeyboardBlocker : IDisposable
{
    private const int WhKeyboardLl = 13;
    private const int WmKeyDown = 0x0100;
    private const int WmSysKeyDown = 0x0104;

    private const int VkTab = 0x09;
    private const int VkEscape = 0x1B;
    private const int VkShift = 0x10;
    private const int VkControl = 0x11;
    private const int VkMenu = 0x12; // Alt
    private const int VkLWin = 0x5B;
    private const int VkRWin = 0x5C;
    private const int VkB = 0x42;

    private delegate IntPtr LowLevelKeyboardProc(int nCode, IntPtr wParam, IntPtr lParam);

    [DllImport("user32.dll", SetLastError = true)]
    private static extern IntPtr SetWindowsHookEx(int idHook, LowLevelKeyboardProc lpfn, IntPtr hMod, uint dwThreadId);

    [DllImport("user32.dll", SetLastError = true)]
    private static extern bool UnhookWindowsHookEx(IntPtr hhk);

    [DllImport("user32.dll")]
    private static extern IntPtr CallNextHookEx(IntPtr hhk, int nCode, IntPtr wParam, IntPtr lParam);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode)]
    private static extern IntPtr GetModuleHandle(string? lpModuleName);

    [DllImport("user32.dll")]
    private static extern short GetAsyncKeyState(int vKey);

    private readonly LowLevelKeyboardProc _callback;
    private IntPtr _hook = IntPtr.Zero;

    public KeyboardBlocker()
    {
        _callback = HookCallback;
    }

    public bool IsEnabled { get; private set; } = true;

    public bool Install()
    {
        if (_hook != IntPtr.Zero)
        {
            return true;
        }

        _hook = SetWindowsHookEx(WhKeyboardLl, _callback, GetModuleHandle(null), 0);

        return _hook != IntPtr.Zero;
    }

    public void SetEnabled(bool enabled) => IsEnabled = enabled;

    private IntPtr HookCallback(int nCode, IntPtr wParam, IntPtr lParam)
    {
        if (nCode >= 0 && IsEnabled && (wParam == WmKeyDown || wParam == WmSysKeyDown))
        {
            var virtualKey = Marshal.ReadInt32(lParam);

            if (IsBlocked(virtualKey))
            {
                return 1; // telan tombol
            }
        }

        return CallNextHookEx(_hook, nCode, wParam, lParam);
    }

    private static bool IsBlocked(int virtualKey)
    {
        var ctrl = (GetAsyncKeyState(VkControl) & 0x8000) != 0;
        var alt = (GetAsyncKeyState(VkMenu) & 0x8000) != 0;
        var shift = (GetAsyncKeyState(VkShift) & 0x8000) != 0;

        // Hotkey admin tidak boleh diblokir.
        if (ctrl && alt && shift && virtualKey == VkB)
        {
            return false;
        }

        return virtualKey switch
        {
            VkLWin or VkRWin => true,
            VkTab when alt => true,
            VkEscape when alt || ctrl => true, // Alt+Esc, Ctrl+Esc, Ctrl+Shift+Esc
            _ => false,
        };
    }

    public void Dispose()
    {
        if (_hook != IntPtr.Zero)
        {
            UnhookWindowsHookEx(_hook);
            _hook = IntPtr.Zero;
        }
    }
}
