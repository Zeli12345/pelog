using Microsoft.Win32;

namespace BalilogKiosk.App.Services;

/// <summary>
/// Hardening kiosk untuk AKUN SISWA SAAT INI (HKCU) — tanpa hak admin,
/// sehingga akun Admin IT di komputer yang sama tidak pernah terpengaruh.
/// Semua perubahan bisa dikembalikan dengan Suspend().
/// </summary>
public static class KioskHardening
{
    private static readonly string[] DisallowedPrograms =
    [
        "powershell.exe",
        "pwsh.exe",
        "wt.exe",
        "cmd.exe",
        "regedit.exe",
        "taskmgr.exe",
        "mmc.exe",
        "msconfig.exe",
        "control.exe",
    ];

    public static void Apply()
    {
        SetDword(@"Software\Microsoft\Windows\CurrentVersion\Policies\System", "DisableTaskMgr", 1);
        SetDword(@"Software\Microsoft\Windows\CurrentVersion\Policies\System", "DisableRegistryTools", 1);
        SetDword(@"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer", "NoRun", 1);
        SetDword(@"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer", "NoControlPanel", 1);

        // Path kanonik untuk "Prevent access to the command prompt".
        SetDword(@"Software\Policies\Microsoft\Windows\System", "DisableCMD", 1);

        // Blokir daftar program berbahaya per-user.
        using var explorer = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer");
        explorer?.SetValue("DisallowRun", 1, RegistryValueKind.DWord);

        using var disallowRun = Registry.CurrentUser.CreateSubKey(@"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer\DisallowRun");

        if (disallowRun is not null)
        {
            var index = 1;

            foreach (var program in DisallowedPrograms)
            {
                disallowRun.SetValue(index.ToString(), program, RegistryValueKind.String);
                index++;
            }
        }
    }

    /// <summary>Menangguhkan hardening (MODE ADMIN) — hanya menghapus nilai yang kita pasang.</summary>
    public static void Suspend()
    {
        DeleteValue(@"Software\Microsoft\Windows\CurrentVersion\Policies\System", "DisableTaskMgr");
        DeleteValue(@"Software\Microsoft\Windows\CurrentVersion\Policies\System", "DisableRegistryTools");
        DeleteValue(@"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer", "NoRun");
        DeleteValue(@"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer", "NoControlPanel");
        DeleteValue(@"Software\Policies\Microsoft\Windows\System", "DisableCMD");
        DeleteValue(@"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer", "DisallowRun");

        try
        {
            Registry.CurrentUser.DeleteSubKeyTree(
                @"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer\DisallowRun",
                throwOnMissingSubKey: false);
        }
        catch (Exception)
        {
            // abaikan
        }
    }

    private static void SetDword(string path, string name, int value)
    {
        using var key = Registry.CurrentUser.CreateSubKey(path);
        key?.SetValue(name, value, RegistryValueKind.DWord);
    }

    private static void DeleteValue(string path, string name)
    {
        using var key = Registry.CurrentUser.OpenSubKey(path, writable: true);
        key?.DeleteValue(name, throwOnMissingValue: false);
    }
}
