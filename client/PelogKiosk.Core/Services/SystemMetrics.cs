using System.Diagnostics;
using System.Runtime.InteropServices;

namespace PelogKiosk.Core.Services;

/// <summary>
/// Metrik sistem ringan untuk dashboard: CPU %, RAM (%, total GB), dan nama GPU.
/// Semua kegagalan mengembalikan null agar tidak pernah mengganggu sesi.
/// </summary>
public static class SystemMetrics
{
    [StructLayout(LayoutKind.Sequential)]
    private struct MemoryStatusEx
    {
        public uint Length;
        public uint MemoryLoad;
        public ulong TotalPhys;
        public ulong AvailPhys;
        public ulong TotalPageFile;
        public ulong AvailPageFile;
        public ulong TotalVirtual;
        public ulong AvailVirtual;
        public ulong AvailExtendedVirtual;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct FileTime
    {
        public uint Low;
        public uint High;

        public readonly ulong Value => ((ulong)High << 32) | Low;
    }

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool GlobalMemoryStatusEx(ref MemoryStatusEx buffer);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool GetSystemTimes(out FileTime idleTime, out FileTime kernelTime, out FileTime userTime);

    private static ulong _prevIdle;
    private static ulong _prevKernel;
    private static ulong _prevUser;
    private static bool _hasPrevious;

    private static string? _gpuName;
    private static bool _gpuResolved;

    /// <summary>
    /// CPU % sejak pemanggilan sebelumnya (panggil berkala, mis. tiap heartbeat).
    /// Pemanggilan pertama hanya menyimpan sampel dasar dan mengembalikan null.
    /// </summary>
    public static int? CpuUsagePercent()
    {
        try
        {
            if (!GetSystemTimes(out var idle, out var kernel, out var user))
            {
                return null;
            }

            var idleTime = idle.Value;
            var kernelTime = kernel.Value;
            var userTime = user.Value;

            if (!_hasPrevious)
            {
                _prevIdle = idleTime;
                _prevKernel = kernelTime;
                _prevUser = userTime;
                _hasPrevious = true;

                return null;
            }

            var idleDelta = idleTime - _prevIdle;
            var totalDelta = (kernelTime - _prevKernel) + (userTime - _prevUser);

            _prevIdle = idleTime;
            _prevKernel = kernelTime;
            _prevUser = userTime;

            if (totalDelta == 0)
            {
                return null;
            }

            var busy = totalDelta > idleDelta ? totalDelta - idleDelta : 0;

            return (int)Math.Clamp(Math.Round(busy * 100.0 / totalDelta), 0, 100);
        }
        catch (Exception)
        {
            return null;
        }
    }

    /// <summary>RAM terpakai (%) dan total (GB).</summary>
    public static (int? UsedPercent, int? TotalGb) RamUsage()
    {
        try
        {
            var status = new MemoryStatusEx { Length = (uint)Marshal.SizeOf<MemoryStatusEx>() };

            if (!GlobalMemoryStatusEx(ref status))
            {
                return (null, null);
            }

            var totalGb = (int)(status.TotalPhys / (1024UL * 1024 * 1024));

            return ((int)Math.Clamp(status.MemoryLoad, 0, 100), totalGb);
        }
        catch (Exception)
        {
            return (null, null);
        }
    }

    /// <summary>Nama GPU pertama (dibaca sekali lalu di-cache; null bila tidak terdeteksi).</summary>
    public static string? GpuName()
    {
        if (_gpuResolved)
        {
            return _gpuName;
        }

        _gpuResolved = true;

        try
        {
            var startInfo = new ProcessStartInfo("wmic", "path win32_VideoController get name")
            {
                RedirectStandardOutput = true,
                UseShellExecute = false,
                CreateNoWindow = true,
            };

            using var process = Process.Start(startInfo);

            if (process is null)
            {
                return null;
            }

            var output = process.StandardOutput.ReadToEnd();
            process.WaitForExit(3000);

            var name = output
                .Split('\n')
                .Select(line => line.Trim())
                .FirstOrDefault(line => line.Length > 0 && !line.Equals("Name", StringComparison.OrdinalIgnoreCase));

            if (name is { Length: > 120 })
            {
                name = name[..120];
            }

            _gpuName = name;

            return _gpuName;
        }
        catch (Exception)
        {
            return null;
        }
    }
}
