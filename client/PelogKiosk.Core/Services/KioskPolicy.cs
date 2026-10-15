using Microsoft.Win32;

namespace PelogKiosk.Core.Services;

/// <summary>
/// Satu sumber kebenaran untuk kebijakan hardening HKCU kiosk — cerminan
/// dari <c>installer/hardening.ps1 -Apply</c>. Semua komponen (kiosk, alat
/// PELOG-Revert) memakai definisi ini agar tidak pernah saling meleset.
/// </summary>
/// <remarks>
/// Catatan penting: sebagian key <c>Policies</c> dilindungi ACL Windows —
/// penulisan/penghapusan bisa ditolak untuk proses non-elevated. Karena itu
/// seluruh API penghapusan mengembalikan status (false bila masih ada sisa)
/// dan penelepon wajib punya fallback (mis. Scheduled Task elevated).
/// </remarks>
public static class KioskPolicy
{
    public const string SystemPolicyKey = @"Software\Microsoft\Windows\CurrentVersion\Policies\System";

    public const string ExplorerPolicyKey = @"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer";

    public const string CmdPolicyKey = @"Software\Policies\Microsoft\Windows\System";

    public const string DisallowRunKey = ExplorerPolicyKey + @"\DisallowRun";

    /// <summary>Nilai-nilai yang ditulis -Apply pada HKCU.</summary>
    public static readonly (string Key, string ValueName)[] PolicyValues =
    [
        (SystemPolicyKey, "DisableTaskMgr"),
        (SystemPolicyKey, "DisableRegistryTools"),
        (ExplorerPolicyKey, "NoRun"),
        (ExplorerPolicyKey, "NoControlPanel"),
        (ExplorerPolicyKey, "DisallowRun"),
        (CmdPolicyKey, "DisableCMD"),
    ];

    /// <summary>Program-program yang diblokir melalui DisallowRun.</summary>
    public static readonly string[] DisallowedPrograms =
    [
        "powershell.exe", "pwsh.exe", "wt.exe", "cmd.exe",
        "regedit.exe", "taskmgr.exe", "mmc.exe", "msconfig.exe", "control.exe",
    ];

    /// <summary>
    /// True bila MINIMAL satu kebijakan hardening masih terpasang pada hive.
    /// Pembacaan biasanya diizinkan walau penulisan tidak.
    /// </summary>
    public static bool AnyPolicyPresent(
        RegistryKey baseHive,
        string systemKeyPath,
        string explorerKeyPath,
        string cmdKeyPath)
    {
        if (ValueExists(baseHive, systemKeyPath, "DisableTaskMgr") ||
            ValueExists(baseHive, systemKeyPath, "DisableRegistryTools"))
        {
            return true;
        }

        if (ValueExists(baseHive, explorerKeyPath, "NoRun") ||
            ValueExists(baseHive, explorerKeyPath, "NoControlPanel") ||
            ValueExists(baseHive, explorerKeyPath, "DisallowRun") ||
            SubKeyExists(baseHive, explorerKeyPath, "DisallowRun"))
        {
            return true;
        }

        return ValueExists(baseHive, cmdKeyPath, "DisableCMD");
    }

    /// <summary>Versi cepat untuk HKCU proses ini (dipakai kiosk & kelas pelanggan).</summary>
    public static bool AnyPolicyPresentForCurrentUser()
    {
        return AnyPolicyPresent(Registry.CurrentUser, SystemPolicyKey, ExplorerPolicyKey, CmdPolicyKey);
    }

    /// <summary>
    /// Menghapus SEMUA kebijakan hardening dari hive yang diberikan.
    /// Idempoten dan best-effort per key; mengembalikan true hanya bila
    /// kebijakan benar-benar bersih setelah operasi.
    /// </summary>
    public static bool Remove(
        RegistryKey baseHive,
        string systemKeyPath,
        string explorerKeyPath,
        string cmdKeyPath)
    {
        TryCleanKey(baseHive, systemKeyPath, ["DisableTaskMgr", "DisableRegistryTools"], deleteDisallowRun: false);
        TryCleanKey(baseHive, explorerKeyPath, ["NoRun", "NoControlPanel", "DisallowRun"], deleteDisallowRun: true);
        TryCleanKey(baseHive, cmdKeyPath, ["DisableCMD"], deleteDisallowRun: false);

        return !AnyPolicyPresent(baseHive, systemKeyPath, explorerKeyPath, cmdKeyPath);
    }

    /// <summary>
    /// Melepaskan kebijakan kiosk untuk pengguna menjalankan proses ini
    /// (HKCU sendiri — tanpa UAC). Mengembalikan true hanya bila benar-benar
    /// bersih; bila false (key dilindungi ACL), penelepon harus menjalankan
    /// fallback elevated (Scheduled Task).
    /// </summary>
    public static bool RemoveForCurrentUser()
    {
        return Remove(Registry.CurrentUser, SystemPolicyKey, ExplorerPolicyKey, CmdPolicyKey);
    }

    private static void TryCleanKey(
        RegistryKey baseHive,
        string subPath,
        string[] valueNames,
        bool deleteDisallowRun)
    {
        try
        {
            using var key = baseHive.OpenSubKey(subPath, writable: true);

            if (key is null)
            {
                return; // key tidak ada — sudah bersih.
            }

            foreach (var valueName in valueNames)
            {
                key.DeleteValue(valueName, throwOnMissingValue: false);
            }

            if (deleteDisallowRun)
            {
                key.DeleteSubKeyTree("DisallowRun", throwOnMissingSubKey: false);
            }
        }
        catch (Exception)
        {
            // Key dilindungi ACL — penelepon wajib menjalankan fallback elevated.
        }
    }

    private static bool ValueExists(RegistryKey baseHive, string subPath, string valueName)
    {
        try
        {
            using var key = baseHive.OpenSubKey(subPath);

            return key?.GetValue(valueName) is not null;
        }
        catch (Exception)
        {
            return false;
        }
    }

    private static bool SubKeyExists(RegistryKey baseHive, string parentPath, string subKeyName)
    {
        try
        {
            using var parent = baseHive.OpenSubKey(parentPath);

            return parent?.GetSubKeyNames().Contains(subKeyName, StringComparer.OrdinalIgnoreCase) is true;
        }
        catch (Exception)
        {
            return false;
        }
    }
}
