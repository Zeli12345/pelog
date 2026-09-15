using BalilogKiosk.App.Services;
using BalilogKiosk.Core.Data;
using Microsoft.Win32;
using Timer = System.Windows.Forms.Timer;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Runtime sesi aktif TANPA jendela dan TANPA ikon tray.
/// Mengelola heartbeat, sinkronisasi, screenshot terjadwal, dan pengakhiran sesi
/// melalui hotkey Ctrl+Alt+S. Saat Windows dimatikan / restart / log off:
/// - log off: sesi ditutup otomatis (alasan "shutdown");
/// - shutdown / restart: ditahan lebih dulu jika ada sesi berjalan supaya siswa
///   mengisi refleksi (wajib), lalu komputer dimatikan;
/// - jalur paksa (mis. "Shut down anyway"): sesi tetap ditutup sebagai "shutdown".
/// Antrean disinkronkan saat aplikasi berjalan lagi.
/// </summary>
public sealed class ActiveSessionRuntime : Form
{
    private const int HotkeyId = 0xB1;
    private const int WmHotkey = 0x0312;
    private const int WmQueryEndSession = 0x0011;
    private const int WmEndSession = 0x0016;
    private const long EndsessionLogoff = 0x80000000L;
    private const uint ModAlt = 0x0001;
    private const uint ModControl = 0x0002;
    private const int VkS = 0x53;
    private const uint SwpNoSize = 0x0001;
    private const uint SwpNoMove = 0x0002;
    private const uint SwpNoActivate = 0x0010;
    private static readonly IntPtr HwndTopmost = new(-1);

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool RegisterHotKey(IntPtr hWnd, int id, uint fsModifiers, uint vk);

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool UnregisterHotKey(IntPtr hWnd, int id);

    [System.Runtime.InteropServices.DllImport("user32.dll", SetLastError = true)]
    private static extern bool ShutdownBlockReasonCreate(
        IntPtr hWnd,
        [System.Runtime.InteropServices.MarshalAs(System.Runtime.InteropServices.UnmanagedType.LPWStr)] string pwszReason);

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool ShutdownBlockReasonDestroy(IntPtr hWnd);

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool SetWindowPos(IntPtr hWnd, IntPtr hWndInsertAfter, int x, int y, int cx, int cy, uint uFlags);

    private readonly AppServices _services;

    private readonly LocalSessionRecord _record;

    private readonly string _displayName;

    private readonly bool _isStudent;

    private readonly Timer _heartbeatTimer = new() { Interval = 60_000 };

    private readonly Timer _syncTimer = new() { Interval = 30_000 };

    private readonly Timer? _screenshotTimer;

    private readonly Timer _sessionEndingFallback = new() { Interval = 2_000 };

    private bool _screenshotTaken;

    private bool _allowClose;

    private bool _finishing;

    private bool _shutdownHandled;

    private bool _shutdownFeedbackDone;

    private bool _shutdownDialogRunning;

    public ActiveSessionRuntime(
        AppServices services,
        LocalSessionRecord record,
        string displayName,
        string subtitle,
        string? subjectName)
    {
        _services = services;
        _record = record;
        _displayName = displayName;
        _isStudent = record.UserType == "student";

        // Form tidak pernah ditampilkan — hanya wadah handle untuk timer & hotkey.
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

        RegisterHotKey(Handle, HotkeyId, ModControl | ModAlt, VkS);
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
            var isLogoff = (m.LParam.ToInt64() & EndsessionLogoff) != 0;

            // Log off: sesi ditutup, shutdown tidak pernah dihambat.
            if (isLogoff || !_record.IsOpen || _shutdownFeedbackDone)
            {
                CloseForShutdown();
                m.Result = new IntPtr(1);

                return;
            }

            // Shutdown / restart saat sesi berjalan: tahan dulu (kembalikan FALSE)
            // supaya siswa mengisi refleksi sebelum laptop benar-benar mati.
            if (BeginShutdownFeedback())
            {
                m.Result = IntPtr.Zero;

                return;
            }

            // Sesi sedang diakhiri lewat alur normal: jangan hambat shutdown.
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

            m.Result = IntPtr.Zero;

            return;
        }

        base.WndProc(ref m);
    }

    /// <summary>
    /// Menahan shutdown/restart lalu menampilkan refleksi wajib. Windows menampilkan
    /// alasan penahanan di layar shutdown. Bila siswa membatalkan, percobaan shutdown
    /// dihentikan; bila tersimpan, komputer langsung dimatikan.
    /// Mengembalikan false bila penahanan tidak dilakukan (shutdown boleh lanjut).
    /// </summary>
    private bool BeginShutdownFeedback()
    {
        if (_shutdownDialogRunning || _finishing)
        {
            return false;
        }

        _shutdownDialogRunning = true;

        SafeBlockReason(create: true);

        // Penanda: jika proses ini dibunuh paksa oleh Windows sebelum sesi sempat
        // ditutup, boot berikutnya mencatat sesi sebagai "shutdown" (bukan "recovery").
        _services.Store.SetKv("shutdown_pending", _record.SessionUuid);

        LogShutdown("blocked-feedback");

        BeginInvoke(new Action(RunShutdownFeedbackAsync));

        return true;
    }

    private async void RunShutdownFeedbackAsync()
    {
        try
        {
            var (confirmed, feedbackText, comprehension) = await ShowFeedbackAsync(shutdownMode: true);

            if (!confirmed)
            {
                // Dibatalkan: hentikan percobaan shutdown, sesi tetap berjalan.
                SafeBlockReason(create: false);
                _services.Store.SetKv("shutdown_pending", "");
                _shutdownDialogRunning = false;

                LogShutdown("feedback-cancelled");

                return;
            }

            _shutdownFeedbackDone = true;

            StopTimers();
            _services.Sessions.Close(_record, feedbackText, comprehension, "shutdown");

            LogShutdown("feedback-saved");

            // Lapor server maksimal singkat; sisa antrean menyusul setelah boot.
            try
            {
                await _services.Sessions.TryRemoteEndAsync(_record).WaitAsync(TimeSpan.FromSeconds(3));
            }
            catch (Exception)
            {
                // Diabaikan — antrean lokal akan menyusul saat aplikasi jalan lagi.
            }

            await _services.Sync.PushSessionsAsync();

            _services.Store.SetKv("shutdown_pending", "");
            SafeBlockReason(create: false);
            _shutdownDialogRunning = false;

            if (ShutdownController.PowerOff())
            {
                return;
            }

            // Hak shutdown ditolak: minta siswa mematikan manual; kiosk kembali terkunci.
            LogShutdown("poweroff-manual");

            MessageBox.Show(
                "Refleksi tersimpan. Silakan matikan laptop dengan menu Power atau tombol power.",
                "BALI-LOG",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);

            ShutdownRuntime();
        }
        catch (Exception)
        {
            SafeBlockReason(create: false);
            _services.Store.SetKv("shutdown_pending", "");
            _shutdownDialogRunning = false;
        }
    }

    private async void OnHeartbeatTick(object? sender, EventArgs e)
    {
        if (!_record.IsOpen)
        {
            return;
        }

        _services.Sessions.Heartbeat(_record);

        var data = await _services.Sessions.TryRemoteHeartbeatAsync(_record);

        if (data is { Active: false })
        {
            // Sesi ditutup dari sisi server (mis. oleh admin).
            _record.LastHeartbeatAt = _services.Clock.Now;
            _record.EndedAtClient ??= _services.Clock.Now;
            _record.CloseReason = data.CloseReason ?? "admin";
            _record.State = "synced";
            _services.Store.SaveSession(_record);

            ShutdownRuntime();
        }
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

        _screenshotTaken = true;

        var config = _services.Sync.LoadCachedConfig();

        var path = ScreenCapture.CaptureToFile(
            _services.ScreenshotDirectory,
            _record.SessionUuid,
            config.ImageFormat,
            config.MaxWidth,
            config.WebpQuality,
            config.JpegQuality);

        if (path is null)
        {
            return;
        }

        _services.Sessions.AttachScreenshot(_record, path);

        await _services.Sync.PushScreenshotsAsync();
    }

    /// <summary>Mengakhiri sesi (dipanggil hotkey Ctrl+Alt+S).</summary>
    public async Task EndSessionAsync()
    {
        if (_finishing || _shutdownDialogRunning)
        {
            return;
        }

        _finishing = true;
        StopTimers();

        if (_isStudent)
        {
            var (confirmed, feedbackText, comprehension) = await ShowFeedbackAsync();

            if (!confirmed)
            {
                _finishing = false;
                StartTimers();

                return;
            }

            _services.Sessions.Close(_record, feedbackText, comprehension);
        }
        else
        {
            _services.Sessions.Close(_record, null, null);
        }

        await _services.Sessions.TryRemoteEndAsync(_record);
        await _services.Sync.PushSessionsAsync();
        await _services.Sync.PushScreenshotsAsync();

        ShutdownRuntime();
    }

    /// <summary>
    /// Menampilkan form refleksi TANPA modal agar timer heartbeat/sinkronisasi
    /// tetap berjalan selama siswa mengisi. Pada mode shutdown, form dijaga
    /// tetap di atas agar tidak tertutup layar "menutup aplikasi" milik Windows.
    /// </summary>
    private async Task<(bool Confirmed, string? Feedback, string? Comprehension)> ShowFeedbackAsync(bool shutdownMode = false)
    {
        using var feedback = new FeedbackForm(_displayName, shutdownMode)
        {
            TopMost = true,
        };

        var completion = new TaskCompletionSource<(bool, string?, string?)>(
            TaskCreationOptions.RunContinuationsAsynchronously);

        Timer? keepOnTop = null;

        if (shutdownMode)
        {
            keepOnTop = new Timer { Interval = 700 };
            keepOnTop.Tick += (_, _) =>
            {
                if (!feedback.IsDisposed)
                {
                    SetWindowPos(feedback.Handle, HwndTopmost, 0, 0, 0, 0, SwpNoMove | SwpNoSize | SwpNoActivate);
                }
            };
        }

        feedback.FormClosed += (_, _) =>
        {
            keepOnTop?.Stop();
            keepOnTop?.Dispose();

            completion.TrySetResult((
                feedback.DialogResult == DialogResult.OK,
                feedback.Feedback,
                feedback.Comprehension));
        };

        feedback.Show();

        keepOnTop?.Start();

        return await completion.Task;
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
    /// Hook cadangan untuk log off / shutdown non-jendela. Log off langsung ditutup;
    /// shutdown dibiarkan ditangani jalur WM_QUERYENDSESSION (yang bisa menahan),
    /// dengan pengaman: bila pesan jendela tidak datang dalam 2 detik, sesi ditutup.
    /// </summary>
    private void OnSystemSessionEnding(object sender, SessionEndingEventArgs e)
    {
        if (e.Reason == SessionEndReasons.Logoff)
        {
            CloseForShutdown();

            _allowClose = true;

            return;
        }

        if (_shutdownDialogRunning || _shutdownFeedbackDone)
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

        if (_shutdownDialogRunning || _shutdownFeedbackDone || _shutdownHandled)
        {
            return;
        }

        CloseForShutdown();

        _allowClose = true;
    }

    /// <summary>
    /// Menutup sesi untuk shutdown/restart/logoff. Dipanggil dari dua jalur
    /// (SystemEvents.SessionEnding dan WM_QUERYENDSESSION) sehingga diberi
    /// penjaga idempoten. Tidak pernah melempar exception.
    /// </summary>
    private void CloseForShutdown()
    {
        if (_shutdownHandled)
        {
            return;
        }

        _shutdownHandled = true;

        try
        {
            LogShutdown("shutdown");

            StopTimers();
            _sessionEndingFallback.Stop();

            if (_record.IsOpen)
            {
                _services.Sessions.Close(_record, null, null, "shutdown");
                _services.Store.SetKv("shutdown_pending", "");

                try
                {
                    _services.Sessions.TryRemoteEndAsync(_record).Wait(TimeSpan.FromSeconds(2));
                }
                catch (Exception)
                {
                    // Diabaikan — antrean lokal akan menyusul saat aplikasi jalan lagi.
                }
            }

            _shutdownFeedbackDone = true;

            if (IsHandleCreated)
            {
                SafeBlockReason(create: false);
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

    /// <summary>
    /// Menulis/menghapus alasan pemblokiran shutdown di layar Windows.
    /// Opsional: bila API gagal, pemblokiran tetap berjalan lewat nilai balik
    /// WM_QUERYENDSESSION (FALSE) sehingga aplikasi tidak pernah ikut gagal.
    /// </summary>
    private void SafeBlockReason(bool create)
    {
        try
        {
            if (!IsHandleCreated)
            {
                return;
            }

            if (create)
            {
                ShutdownBlockReasonCreate(Handle, "BALI-LOG: klik Cancel/Batal, lalu isi refleksi belajar supaya laptop bisa dimatikan.");
            }
            else
            {
                ShutdownBlockReasonDestroy(Handle);
            }
        }
        catch (Exception)
        {
            // API opsional.
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
