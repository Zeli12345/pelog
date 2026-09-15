using System.Diagnostics;
using Microsoft.Win32;

namespace BalilogKiosk.App.Services;

/// <summary>
/// Menangguhkan / menerapkan kembali kebijakan hardening kiosk lewat Scheduled Task
/// elevated yang dibuat installer. Cara ini dipakai karena saat hardening aktif,
/// peluncuran powershell.exe lewat shell diblokir DisallowRun (jadi runas + UAC
/// tidak mungkin), sementara Task Scheduler menjalankan proses langsung.
/// </summary>
internal static class KioskHardening
{
    public const string SuspendTaskName = "BALILogHardeningSuspend";

    public const string ApplyTaskName = "BALILogHardeningApply";

    private const string PolicyKey = @"Software\Policies\Microsoft\Windows\System";

    private const string ProbeValue = "DisableTaskMgr";

    public static bool Suspend() => RunTask(SuspendTaskName, policyShouldExist: false);

    public static bool Apply() => RunTask(ApplyTaskName, policyShouldExist: true);

    private static bool RunTask(string taskName, bool policyShouldExist)
    {
        if (!RunTaskProcess(taskName))
        {
            return false;
        }

        return WaitForPolicy(policyShouldExist, TimeSpan.FromSeconds(10));
    }

    private static bool RunTaskProcess(string taskName)
    {
        try
        {
            var startInfo = new ProcessStartInfo
            {
                FileName = "schtasks.exe",
                Arguments = $"/Run /TN \"{taskName}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
            };

            using var process = Process.Start(startInfo);
            process?.WaitForExit(15_000);

            return process?.ExitCode == 0;
        }
        catch (Exception)
        {
            return false;
        }
    }

    private static bool WaitForPolicy(bool shouldExist, TimeSpan timeout)
    {
        var deadline = DateTime.UtcNow + timeout;

        while (true)
        {
            if (IsPolicyPresent() == shouldExist)
            {
                return true;
            }

            if (DateTime.UtcNow >= deadline)
            {
                return false;
            }

            Thread.Sleep(400);
        }
    }

    private static bool IsPolicyPresent()
    {
        using var key = Registry.CurrentUser.OpenSubKey(PolicyKey);

        return key?.GetValue(ProbeValue) is not null;
    }
}
