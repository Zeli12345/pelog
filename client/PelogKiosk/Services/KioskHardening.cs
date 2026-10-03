using System.Diagnostics;
using Microsoft.Win32;

namespace PelogKiosk.App.Services;

/// <summary>
/// Menangguhkan / menerapkan kembali kebijakan hardening kiosk lewat Scheduled Task
/// elevated yang dibuat installer. Cara ini dipakai karena saat hardening aktif,
/// peluncuran powershell.exe lewat shell diblokir DisallowRun (jadi runas + UAC
/// tidak mungkin), sementara Task Scheduler menjalankan proses langsung.
/// Setelah penangguhan, shell (explorer) dimuat ulang karena Explorer yang sudah
/// berjalan dapat men-cache kebijakan lama ("Accessing the resource ... has been
/// disallowed") sampai dimuat ulang / logon berikutnya.
/// </summary>
internal static class KioskHardening
{
    public const string SuspendTaskName = "PelogHardeningSuspend";

    public const string ApplyTaskName = "PelogHardeningApply";

    // Lokasi kanonik DisableTaskMgr/DisableRegistryTools (lihat hardening.ps1
    // $polSystem) — bukan Software\Policies\... yang hanya dipakai DisableCMD.
    private const string SystemPolicyKey = @"Software\Microsoft\Windows\CurrentVersion\Policies\System";

    private const string ExplorerPolicyKey = @"Software\Microsoft\Windows\CurrentVersion\Policies\Explorer";

    private const string CmdPolicyKey = @"Software\Policies\Microsoft\Windows\System";

    private static readonly (string Key, string Value)[] PolicyProbes =
    [
        (SystemPolicyKey, "DisableTaskMgr"),
        (SystemPolicyKey, "DisableRegistryTools"),
        (ExplorerPolicyKey, "NoRun"),
        (ExplorerPolicyKey, "NoControlPanel"),
        (ExplorerPolicyKey, "DisallowRun"),
        (CmdPolicyKey, "DisableCMD"),
    ];

    // Serialisasi Apply/Suspend agar dua permintaan yang berdekatan tidak saling
    // menimpa (mis. sesi dimulai saat Apply startup masih berjalan).
    private static readonly SemaphoreSlim Gate = new(1, 1);

    public static bool Suspend()
    {
        if (!Gate.Wait(TimeSpan.FromSeconds(30)))
        {
            return false;
        }

        try
        {
            if (!RunTask(SuspendTaskName, policyShouldExist: false))
            {
                return false;
            }

            // Muat ulang shell di latar belakang agar kebijakan lama tidak tersisa
            // di Explorer yang sedang berjalan.
            Task.Run(RestartExplorer);

            return true;
        }
        finally
        {
            Gate.Release();
        }
    }

    public static bool Apply()
    {
        if (!Gate.Wait(TimeSpan.FromSeconds(30)))
        {
            return false;
        }

        try
        {
            return RunTask(ApplyTaskName, policyShouldExist: true);
        }
        finally
        {
            Gate.Release();
        }
    }

    /// <summary>
    /// Menjalankan ulang explorer.exe (normal, bukan elevated) dengan aman.
    /// Dipakai setelah penangguhan dan tersedia juga sebagai tombol manual
    /// di mode admin.
    /// </summary>
    public static void RestartExplorer()
    {
        try
        {
            using (var kill = Process.Start(new ProcessStartInfo
            {
                FileName = "taskkill.exe",
                Arguments = "/f /im explorer.exe",
                UseShellExecute = false,
                CreateNoWindow = true,
            }))
            {
                kill?.WaitForExit(5000);
            }

            Thread.Sleep(800);
        }
        catch (Exception)
        {
            // Explorer mungkin tidak berjalan; abaikan.
        }

        try
        {
            Process.Start(new ProcessStartInfo
            {
                FileName = "explorer.exe",
                UseShellExecute = false,
            });
        }
        catch (Exception)
        {
            // Shell akan pulih saat logon berikutnya; abaikan.
        }
    }

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

    /// <summary>
    /// True bila SALAH SATU kebijakan hardening masih terpasang.
    /// Penangguhan baru dianggap berhasil bila semuanya benar-benar hilang.
    /// </summary>
    private static bool IsPolicyPresent()
    {
        foreach (var (keyPath, valueName) in PolicyProbes)
        {
            using var key = Registry.CurrentUser.OpenSubKey(keyPath);

            if (key?.GetValue(valueName) is not null)
            {
                return true;
            }
        }

        return false;
    }
}
