using PelogKiosk.App.Services;
using PelogKiosk.Core.Data;
using Microsoft.Win32;
using Timer = System.Windows.Forms.Timer;

namespace PelogKiosk.App.Forms;

/// <summary>
/// Runtime sesi aktif TANPA jendela dan TANPA ikon tray.
/// Mengelola heartbeat, sinkronisasi, screenshot terjadwal, dan pengakhiran sesi
/// melalui hotkey Ctrl+Alt+S (cadangan Ctrl+Alt+E bila registrasi gagal).
/// Saat Windows dimatikan / restart / log off:
/// - sesi lokal ditutup dengan alasan "shutdown";
/// - laporan ke server (maks. 3 detik) dan flush antrean (maks. 3 detik) dijalankan;
/// - shutdown TIDAK PERNAH ditahan: tanpa ShutdownBlockReasonCreate dan tanpa
///   mengembalikan FALSE dari WM_QUERYENDSESSION.
/// Antrean yang belum terkirim disinkronkan saat aplikasi berjalan lagi.
/// </summary>
public sealed class ActiveSessionRuntime : Form
{
    private const int HotkeyId = 0xB1;
    private const int WmHotkey = 0x0312;
    private const int WmQueryEndSession = 0x0011;
    private const int WmEndSession = 0x0016;
    private const uint ModAlt = 0x0001;
    private const uint ModControl = 0x0002;
    private const int VkS = 0x53;
    private const int VkE = 0x45;

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool RegisterHotKey(IntPtr hWnd, int id, uint fsModifiers, uint vk);

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool UnregisterHotKey(IntPtr hWnd, int id);

    private readonly AppServices _services;

    private readonly LocalSessionRecord _record;

    private readonly bool _isStudent;

    private readonly Timer _heartbeatTimer = new() { Interval = 60_000 };

    private readonly Timer _syncTimer = new() { Interval = 30_000 };

    private readonly Timer? _screenshotTimer;

    private readonly Timer _sessionEndingFallback = new() { Interval = 2_000 };

    private bool _screenshotTaken;

    private bool _allowClose;

    private bool _finishing;

    private bool _shutdownHandled;

    private bool _revokedHandled;

    public ActiveSessionRuntime(AppServices services, LocalSessionRecord record)
    {
        _services = services;
        _record = record;
        _isStudent = record.UserType == "student";

        // Form tidak pernah ditampilkan â€” hanya wadah handle untuk timer & hotkey.
        ShowInTaskbar = false;
        FormBorderStyle = FormBorderStyle.None;
        WindowState = FormWindowState.Minimized;
        StartPosition = FormStartPosition.Manual;
        Location = new Point(-32000, -32000);
        Size = new Size(1, 1);

        var config = _services.Sync.LoadCachedConfig();
        var minute = _services.Config.TestMode ? _services.Config.TestScreenshotMinute : config.ScreenshotMinute;

        if (_isStudent && config.ScreenshotEnabled && minute > 0)
        {
            _screenshotTimer = new Timer { Interval = Math.Max(1, minute) * 60_000 };
            _screenshotTimer.Tick += OnScreenshotTick;
            _screenshotTimer.Start();
        }

        _heartbeatTimer.Tick += OnHeartbeatTick;
        _syncTimer.Tick += OnSyncTick;

        _heartbeatTimer.Start();
        _syncTimer.Start();

        SystemEvents.SessionEnding += OnSystemSessionEnding;

        _sessionEndingFallback.Tick += OnSessionEndingFallbackTick;

        // Paksa pembuatan handle agar hotkey & timer langsung aktif tanpa menampilkan jendela.
        _ = Handle;
    }

    protected override void OnHandleCreated(EventArgs e)
    {
        base.OnHandleCreated(e);

        RegisterSessionHotkey();
    }

    /// <summary>
    /// Mendaftarkan hotkey pengakhir sesi. Ctrl+Alt+S adalah hotkey utama;
    /// bila gagal (mis. sudah dipakai aplikasi lain), Ctrl+Alt+E dipakai sebagai
    /// cadangan. Kombinasi yang benar-benar aktif selalu dicatat ke log lokal.
    /// </summary>
    private void RegisterSessionHotkey()
    {
        if (RegisterHotKey(Handle, HotkeyId, ModControl | ModAlt, VkS))
        {
            LocalLog.Write(_services.DataDirectory, "hotkey sesi aktif: Ctrl+Alt+S");

            return;
        }

        if (RegisterHotKey(Handle, HotkeyId, ModControl | ModAlt, VkE))
        {
            LocalLog.Write(_services.DataDirectory, "hotkey sesi aktif: Ctrl+Alt+E (Ctrl+Alt+S gagal terdaftar)");

            return;
        }

        LocalLog.Write(_services.DataDirectory, "hotkey sesi GAGAL terdaftar: Ctrl+Alt+S dan Ctrl+Alt+E tidak tersedia");
    }

    protected override void WndProc(ref Message m)
    {
        if (m.Msg == WmHotkey && m.WParam.ToInt32() == HotkeyId)
        {
            _ = EndSessionAsync();

            return;
        }

        if (m.Msg == WmQueryEndSession)
        {
            // Shutdown / restart: tutup sesi lokal, lapor server & flush antrean
            // sebentar, lalu izinkan shutdown (m.Result = 1 / TRUE). Shutdown
            // tidak pernah ditahan lewat ShutdownBlockReasonCreate/FALSE.
            CloseForShutdown();
            m.Result = new IntPtr(1);

            return;
        }

        if (m.Msg == WmEndSession)
        {
            // wParam != 0 berarti sesi memang berakhir (mis. "Shut down anyway");
            // bila shutdown dibatalkan, wParam = 0 dan sesi tetap berjalan.
            if (m.WParam != IntPtr.Zero)
            {
                CloseForShutdown();
            }
            else if (_shutdownHandled)
            {
                // Shutdown/restart dibatalkan aplikasi lain: sesi sudah ditutup,
                // jadi kembalikan kiosk ke layar kunci.
                ShutdownRuntime();
            }

            m.Result = IntPtr.Zero;

            return;
        }

        base.WndProc(ref m);
    }

    private async void OnHeartbeatTick(object? sender, EventArgs e)
    {
        if (!_record.IsOpen)
        {
            return;
        }

        _services.Sessions.Heartbeat(_record);

        var result = await _services.Sessions.TryRemoteHeartbeatAsync(_record);

        if (SelfWipeService.IsRevoked(result))
        {
            HandleDeviceRevoked("heartbeat sesi aktif");

            return;
        }

        if (result.Data is { Active: false })
        {
            // Sesi ditutup dari sisi server (mis. oleh admin).
            _record.LastHeartbeatAt = _services.Clock.Now;
            _record.EndedAtClient ??= _services.Clock.Now;
            _record.CloseReason = result.Data.CloseReason ?? "admin";
            _record.State = "synced";
            _services.Store.SaveSession(_record);

            ShutdownRuntime();
        }
    }

    /// <summary>
    /// Perangkat dihapus dari dashboard (410 device_revoked): jalankan wipe total
    /// tanpa menutup sesi secara normal. Bila SelfWipeOnRevoke dimatikan, kejadian
    /// hanya dicatat sekali. Timer WinForms memanggil ini di UI thread.
    /// </summary>
    private void HandleDeviceRevoked(string reason)
    {
        if (_revokedHandled)
        {
            return;
        }

        _revokedHandled = true;

        if (!_services.Config.SelfWipeOnRevoke)
        {
            LocalLog.Write(
                _services.DataDirectory,
                $"device_revoked ({reason}) â€” SelfWipeOnRevoke=false, wipe tidak dijalankan.");

            return;
        }

        SelfWipeService.Trigger($"sesi aktif: {reason}");
    }

    private async void OnSyncTick(object? sender, EventArgs e)
    {
        if (!await _services.Api.HealthAsync())
        {
            return;
        }

        await _services.Sync.PushSessionsAsync();
        await _services.Sync.PushScreenshotsAsync();

        var config = _services.Sync.LoadCachedConfig();
        var lastBootstrap = _services.Store.GetKv("bootstrap_at");

        if (DateTimeOffset.TryParse(lastBootstrap, out var at) &&
            _services.Clock.Now - at > TimeSpan.FromMinutes(Math.Max(1, config.BootstrapRefreshMinutes)))
        {
            await _services.Sync.RefreshBootstrapAsync();
        }

        // Auto-update: periksa & unduh rilis baru (di-throttle oleh UpdateService).
        await _services.Updates.CheckAndStageAsync(_services.Config.UpdateCheckHours);
    }

    private async void OnScreenshotTick(object? sender, EventArgs e)
    {
        _screenshotTimer?.Stop();

        if (_screenshotTaken || !_record.IsOpen)
        {
            return;
        }

        var config = _services.Sync.LoadCachedConfig();
        var capturedAt = _services.Clock.Now;

        var path = ScreenCapture.CaptureToFile(
            _services.ScreenshotDirectory,
            _record.SessionUuid,
            config.ImageFormat,
            config.MaxWidth,
            config.WebpQuality,
            config.JpegQuality);

        if (path is null)
        {
            // Gagal menangkap/menyandi: jangan tandai sesi sudah punya screenshot,
            // jadwalkan percobaan berikutnya pada interval yang sama.
            _screenshotTimer?.Start();

            return;
        }

        // Tandai hanya setelah capture benar-benar berhasil, supaya kegagalan
        // tidak menghabiskan jatah satu-satunya screenshot sesi.
        _screenshotTaken = true;

        _services.Sessions.AttachScreenshot(_record, path, capturedAt);

        await _services.Sync.PushScreenshotsAsync();
    }

    /// <summary>
    /// Mengakhiri sesi (dipanggil hotkey Ctrl+Alt+S) setelah konfirmasi singkat,
    /// tanpa form tambahan.
    /// </summary>
    public Task EndSessionAsync()
    {
        if (_finishing || _shutdownHandled)
        {
            return Task.CompletedTask;
        }

        _finishing = true;
        StopTimers();

        var confirmed = MessageBox.Show(
            "Akhiri sesi sekarang?",
            "PELOG",
            MessageBoxButtons.YesNo,
            MessageBoxIcon.Question,
            MessageBoxDefaultButton.Button2) == DialogResult.Yes;

        if (!confirmed)
        {
            _finishing = false;
            StartTimers();

            return Task.CompletedTask;
        }

        _services.Sessions.Close(_record, null, null);

        return FinishSessionAsync();
    }

    private async Task FinishSessionAsync()
    {
        await _services.Sessions.TryRemoteEndAsync(_record);
        await _services.Sync.PushSessionsAsync();
        await _services.Sync.PushScreenshotsAsync();

        ShutdownRuntime();
    }

    private void StartTimers()
    {
        _heartbeatTimer.Start();
        _syncTimer.Start();
        _screenshotTimer?.Start();
    }

    private void StopTimers()
    {
        _heartbeatTimer.Stop();
        _syncTimer.Stop();
        _screenshotTimer?.Stop();
    }

    /// <summary>
    /// Hook cadangan untuk log off / shutdown non-jendela. Log off langsung
    /// menutup sesi; shutdown/restart memakai fallback singkat bila pesan
    /// WM_QUERYENDSESSION tidak sampai ke jendela ini. Shutdown tidak pernah
    /// dihambat dari jalur mana pun.
    /// </summary>
    private void OnSystemSessionEnding(object sender, SessionEndingEventArgs e)
    {
        if (e.Reason == SessionEndReasons.Logoff)
        {
            CloseForShutdown();

            _allowClose = true;

            return;
        }

        if (_shutdownHandled)
        {
            return;
        }

        if (!_record.IsOpen)
        {
            CloseForShutdown();

            _allowClose = true;

            return;
        }

        _sessionEndingFallback.Start();
    }

    private void OnSessionEndingFallbackTick(object? sender, EventArgs e)
    {
        _sessionEndingFallback.Stop();

        if (_shutdownHandled)
        {
            return;
        }

        CloseForShutdown();

        _allowClose = true;
    }

    /// <summary>
    /// Menutup sesi untuk shutdown/restart/logoff: simpan lokal (alasan "shutdown"),
    /// lapor server maks. 3 detik, flush antrean maks. 3 detik, lalu izinkan
    /// shutdown. Dipanggil dari beberapa jalur sehingga diberi penjaga idempoten.
    /// Tidak pernah melempar exception.
    /// </summary>
    private void CloseForShutdown()
    {
        if (_shutdownHandled)
        {
            return;
        }

        _shutdownHandled = true;
        _finishing = true;

        try
        {
            LogShutdown("shutdown");

            StopTimers();
            _sessionEndingFallback.Stop();

            if (_record.IsOpen)
            {
                _services.Store.SetKv("shutdown_pending", _record.SessionUuid);
                _services.Sessions.Close(_record, null, null, "shutdown");

                try
                {
                    // Dijalankan di thread latar: menunggu task ini langsung di UI
                    // thread akan deadlock karena kelanjutannya menangkap
                    // synchronization context WinForms. Batas 3 detik.
                    using var endTimeout = new CancellationTokenSource(TimeSpan.FromSeconds(3));

                    Task.Run(() => _services.Sessions.TryRemoteEndAsync(_record, endTimeout.Token))
                        .Wait(TimeSpan.FromSeconds(3));
                }
                catch (Exception)
                {
                    // Diabaikan â€” antrean lokal akan menyusul saat aplikasi jalan lagi.
                }

                try
                {
                    // Batas singkat untuk unggahan antrean: jaringan yang tidak
                    // merespons tidak boleh menahan shutdown.
                    using var flushTimeout = new CancellationTokenSource(TimeSpan.FromSeconds(3));

                    Task.Run(() => _services.Sync.PushSessionsAsync(flushTimeout.Token))
                        .Wait(TimeSpan.FromSeconds(3));
                }
                catch (Exception)
                {
                    // Diabaikan â€” antrean lokal akan menyusul saat aplikasi jalan lagi.
                }

                _services.Store.SetKv("shutdown_pending", "");
            }

            _allowClose = true;
        }
        catch (Exception)
        {
            // Apa pun yang terjadi, jangan halangi shutdown.
        }
    }

    /// <summary>Jejak diagnostik ringan untuk setiap tahap shutdown (berguna saat maintenance).</summary>
    private void LogShutdown(string reason)
    {
        try
        {
            File.AppendAllText(
                Path.Combine(_services.DataDirectory, "shutdown.log"),
                $"{DateTime.Now:O} sesi={_record.SessionUuid} alasan={reason}{Environment.NewLine}");
        }
        catch (Exception)
        {
            // Logging opsional.
        }
    }

    private void ShutdownRuntime()
    {
        StopTimers();
        _sessionEndingFallback.Stop();
        _allowClose = true;

        SystemEvents.SessionEnding -= OnSystemSessionEnding;

        if (Handle != IntPtr.Zero)
        {
            UnregisterHotKey(Handle, HotkeyId);
        }

        Close();
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (!_allowClose && e.CloseReason == CloseReason.UserClosing)
        {
            e.Cancel = true;

            return;
        }

        SystemEvents.SessionEnding -= OnSystemSessionEnding;
        StopTimers();
        _sessionEndingFallback.Stop();

        base.OnFormClosing(e);
    }
}
