using PelogRevert;

namespace PelogRevert.Tests;

/// <summary>
/// Rencana perbaikan (revert) harus mencakup SEMUA artefak PELOG:
/// task terjadwal (termasuk PelogParole), autostart, dan kebijakan HKCU.
/// </summary>
public class RevertPlanTests
{
    [Fact]
    public void ScheduledTasks_mencakup_seluruh_task_PELOG()
    {
        var expected = new HashSet<string>
        {
            "PELOG Kiosk (Logon)",
            "PELOG Kiosk (Watchdog)",
            "PelogAutoUpdate",
            "PelogSelfWipe",
            "PelogParole",
            "PelogHardeningApply",
            "PelogHardeningSuspend",
        };

        Assert.Equal(expected, RevertPlan.ScheduledTasks.ToHashSet());
    }

    [Fact]
    public void PolicyValues_cocok_dengan_definisi_hardening_kiosk()
    {
        var names = RevertPlan.PolicyValues.Select(p => p.ValueName).ToHashSet();

        Assert.Equal(new HashSet<string>
        {
            "DisableTaskMgr", "DisableRegistryTools", "NoRun", "NoControlPanel",
            "DisableCMD", "DisallowRun",
        }, names);
    }

    [Fact]
    public void Konstanta_artefak_lain_terisi()
    {
        Assert.Equal("PelogKiosk", RevertPlan.KioskProcessName);
        Assert.Equal("PelogKiosk", RevertPlan.AutostartRunValueName);
        Assert.Contains("Microsoft\\Windows\\CurrentVersion\\Run", RevertPlan.AutostartRunKeyPath);
        Assert.Equal(@"C:\ProgramData\PELOG", RevertPlan.ProgramDataDirectory);
        Assert.Equal("unins000.exe", RevertPlan.UninstallerName);
    }
}
