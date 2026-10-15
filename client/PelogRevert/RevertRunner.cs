using Microsoft.Win32;
using System.Diagnostics;

namespace PelogRevert;

/// <summary>
/// Eksekusi langkah perbaikan (revert). Semua langkah best-effort; hasil
/// dikirim ke callback dan disalin ke C:\Users\Public\pelog-revert.log.
/// </summary>
public sealed class RevertRunner
{
    private readonly Action<string> _log;

    public RevertRunner(Action<string> log)
    {
        _log = log;
    }

    public void LogLine(string message)
    {
        _log(message);

        try
        {
            File.AppendAllText(
                RevertPlan.PublicLogPath,
                $"{DateTime.Now:yyyy-MM-dd HH:mm:ss}  {message}{Environment.NewLine}");
        }
        catch (Exception)
        {
            // log publik opsional — jangan mengganggu penanganan.
        }
    }

    /// <summary>Hentikan kiosk PELOG (dua ronde agar watchdog tidak sempat menghidupkan ulang).</summary>
    public void StopKiosk()
    {
        for (var round = 1; round <= 2; round++)
        {
            var exit = RunProcess("taskkill.exe", $"/f /im {RevertPlan.KioskProcessName}.exe");
            LogLine($"Hentikan {RevertPlan.KioskProcessName}.exe (ronde {round}) — kode {exit}.");

            if (round < 2)
            {
                Thread.Sleep(1500);
            }
        }
    }

    /// <summary>
    /// Lepaskan kebijakan penguncian dari seluruh hive pengguna yang ter-muat
    /// (mulai dari HKCU proses ini). Bagian penting: jangan menyisakan
    /// NoRun/DisableCMD/DisableTaskMgr/DisableRegistryTools/NoControlPanel.
    /// </summary>
    public void RemovePoliciesEverywhere()
    {
        RemovePoliciesForHive(Registry.CurrentUser, "HKCU");

        foreach (var sid in Registry.Users.GetSubKeyNames())
        {
            if (sid.EndsWith("_Classes", StringComparison.OrdinalIgnoreCase))
            {
                continue;
            }

            if (sid is "S-1-5-18" or "S-1-5-19" or "S-1-5-20")
            {
                continue;
            }

            if (!sid.StartsWith("S-1-5-", StringComparison.OrdinalIgnoreCase))
            {
                continue;
            }

            using var hive = Registry.Users.OpenSubKey(sid, writable: true);

            if (hive is null)
            {
                continue;
            }

            RemovePoliciesForHive(hive, sid);
        }
    }

    private void RemovePoliciesForHive(RegistryKey baseHive, string label)
    {
        try
        {
            using (var key = baseHive.OpenSubKey(RevertPlan.SystemPolicyKey, writable: true))
            {
                key?.DeleteValue("DisableTaskMgr", throwOnMissingValue: false);
                key?.DeleteValue("DisableRegistryTools", throwOnMissingValue: false);
            }

            using (var key = baseHive.OpenSubKey(RevertPlan.ExplorerPolicyKey, writable: true))
            {
                key?.DeleteValue("NoRun", throwOnMissingValue: false);
                key?.DeleteValue("NoControlPanel", throwOnMissingValue: false);
                key?.DeleteValue("DisallowRun", throwOnMissingValue: false);
                key?.DeleteSubKeyTree("DisallowRun", throwOnMissingSubKey: false);
            }

            using (var key = baseHive.OpenSubKey(RevertPlan.CmdPolicyKey, writable: true))
            {
                key?.DeleteValue("DisableCMD", throwOnMissingValue: false);
            }

            LogLine($"Kebijakan penguncian dibersihkan dari {label}.");
        }
        catch (Exception ex)
        {
            LogLine($"Gagal membersihkan kebijakan {label}: {ex.Message}");
        }
    }

    public void DeleteScheduledTasks()
    {
        foreach (var task in RevertPlan.ScheduledTasks)
        {
            var code = RunProcess("schtasks.exe", $"/Delete /F /TN \"{task}\"");
            LogLine($"/Delete task '{task}' — kode {code} (tidak ada = sudah bersih).");
        }
    }

    public void RemoveAutostart()
    {
        try
        {
            using var runKey = Registry.LocalMachine.OpenSubKey(RevertPlan.AutostartRunKeyPath, writable: true);
            runKey?.DeleteValue(RevertPlan.AutostartRunValueName, throwOnMissingValue: false);
            LogLine("Autostart HKLM Run (PelogKiosk) dihapus.");
        }
        catch (Exception ex)
        {
            LogLine($"Gagal menghapus autostart: {ex.Message}");
        }
    }

    /// <summary>
    /// Hapus instalasi + data (opsional): uninstaller senyap PELOG lalu sisa
    /// folder ProgramData dan folder aplikasi.
    /// </summary>
    public void RemoveInstallationData()
    {
        var uninstaller = Path.Combine(RevertPlan.AppDirectory, RevertPlan.UninstallerName);

        if (File.Exists(uninstaller))
        {
            LogLine("Menjalankan uninstaller PELOG (senyap)...");
            try
            {
                using var process = Process.Start(new ProcessStartInfo
                {
                    FileName = uninstaller,
                    Arguments = "/VERYSILENT /SUPPRESSMSGBOXES /NORESTART /NOCLOSEAPPLICATIONS",
                    UseShellExecute = true,
                });

                if (process is not null)
                {
                    if (process.WaitForExit(300_000))
                    {
                        LogLine($"Uninstaller selesai — kode {process.ExitCode}.");
                    }
                    else
                    {
                        try { process.Kill(); } catch (Exception) { }
                        LogLine("Uninstaller > 5 menit — dihentikan; lanjut ke pembersihan manual.");
                    }
                }
            }
            catch (Exception ex)
            {
                LogLine($"Gagal menjalankan uninstaller: {ex.Message}");
            }
        }
        else
        {
            LogLine($"Uninstaller tidak ditemukan di {RevertPlan.AppDirectory} — lewati.");
        }

        foreach (var directory in new[] { RevertPlan.ProgramDataDirectory, RevertPlan.AppDirectory })
        {
            for (var attempt = 1; attempt <= 2; attempt++)
            {
                try
                {
                    if (Directory.Exists(directory))
                    {
                        Directory.Delete(directory, recursive: true);
                    }
                }
                catch (Exception)
                {
                    // dicoba sekali lagi
                }

                if (!Directory.Exists(directory))
                {
                    break;
                }

                Thread.Sleep(1500);
            }

            LogLine(Directory.Exists(directory)
                ? $"Peringatan: folder masih ada ({directory})."
                : $"Folder bersih: {directory}.");
        }
    }

    public void RestartExplorer()
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
            // explorer mungkin tidak berjalan
        }

        try
        {
            Process.Start(new ProcessStartInfo { FileName = "explorer.exe", UseShellExecute = false });
            LogLine("Explorer dimuat ulang — tampilan desktop menyegarkan kebijakan.");
        }
        catch (Exception)
        {
            LogLine("Explorer gagal dimulai — akan pulih saat logon berikutnya.");
        }
    }

    private static int RunProcess(string fileName, string arguments)
    {
        try
        {
            using var process = Process.Start(new ProcessStartInfo
            {
                FileName = fileName,
                Arguments = arguments,
                UseShellExecute = false,
                CreateNoWindow = true,
            });
            process?.WaitForExit(30_000);

            return process?.ExitCode ?? -1;
        }
        catch (Exception)
        {
            return -1;
        }
    }
}
