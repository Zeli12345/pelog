namespace PelogRevert;

/// <summary>
/// Rencana perbaikan: seluruh artefak yang dipasang PELOG kiosk. Definisi ini
/// dipakai PELOG-Revert dan diuji agar selalu mencakup semua yang dibuat
/// installer (task terjadwal, autostart, kebijakan HKCU).
/// </summary>
public static class RevertPlan
{
    public const string KioskProcessName = "PelogKiosk";

    public const string AutostartRunKeyPath = @"SOFTWARE\Microsoft\Windows\CurrentVersion\Run";

    public const string AutostartRunValueName = "PelogKiosk";

    public const string SystemPolicyKey = @"Software\Microsoft\Windows\CurrentVersion\Policies\System";

    public const string ExplorerPolicyKey = @"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer";

    public const string CmdPolicyKey = @"Software\Policies\Microsoft\Windows\System";

    public const string DisallowRunKey = ExplorerPolicyKey + @"\DisallowRun";

    public static readonly (string Key, string ValueName)[] PolicyValues =
    [
        (SystemPolicyKey, "DisableTaskMgr"),
        (SystemPolicyKey, "DisableRegistryTools"),
        (ExplorerPolicyKey, "NoRun"),
        (ExplorerPolicyKey, "NoControlPanel"),
        (ExplorerPolicyKey, "DisallowRun"),
        (CmdPolicyKey, "DisableCMD"),
    ];

    public static readonly string[] ScheduledTasks =
    [
        "PELOG Kiosk (Logon)",
        "PELOG Kiosk (Watchdog)",
        "PelogAutoUpdate",
        "PelogSelfWipe",
        "PelogParole",
        "PelogHardeningApply",
        "PelogHardeningSuspend",
    ];

    public const string ProgramDataDirectory = @"C:\ProgramData\PELOG";

    public const string AppDirectory = @"C:\Program Files\PELOG Kiosk";

    public const string UninstallerName = "unins000.exe";

    public const string PublicLogPath = @"C:\Users\Public\pelog-revert.log";
}
