using BalilogKiosk.App.Services;
using BalilogKiosk.Core.Data;
using Microsoft.Win32;
using Timer = System.Windows.Forms.Timer;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Runtime sesi aktif TANPA jendela dan TANPA ikon tray.
/// Mengelola heartbeat, sinkronisasi, screenshot terjadwal, dan pengakhiran sesi
/// melalui hotkey Ctrl+Alt+S. Saat Windows dimatikan / restart / log off,
/// sesi ditutup otomatis (alasan "shutdown") dan antrean disinkronkan
/// pada saat aplikasi berjalan lagi.
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

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool RegisterHotKey(IntPtr hWnd, int id, uint fsModifiers, uint vk);

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool UnregisterHotKey(IntPtr hWnd, int id);

    private readonly AppServices _services;

    private readonly LocalSessionRecord _record;

    private readonly string _displayName;

    private readonly bool _isStudent;

    private readonly Timer _heartbeatTimer = new() { Interval = 60_000 };

    private readonly Timer _syncTimer = new() { Interval = 30_000 };

    private readonly Timer? _screenshotTimer;

    private bool _screenshotTaken;

    private bool _allowClose;

    private bool _finishing;

    private bool _shutdownHandled;

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

        // Hook shutdown/restart/logoff yang andal: Windows mengirim pesan ini ke
        // semua jendela top-level (termasuk yang tersembunyi). Selalu izinkan
        // shutdown (jangan pernah menghambat) sambil menutup sesi lebih dulu.
        if (m.Msg is WmQueryEndSession or WmEndSession)
        {
            CloseForShutdown();
            m.Result = new IntPtr(1);

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
        if (_finishing)
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
    /// tetap berjalan selama siswa mengisi.
    /// </summary>
    private async Task<(bool Confirmed, string? Feedback, string? Comprehension)> ShowFeedbackAsync()
    {
        using var feedback = new FeedbackForm(_displayName)
        {
            TopMost = true,
        };

        var completion = new TaskCompletionSource<(bool, string?, string?)>(
            TaskCreationOptions.RunContinuationsAsynchronously);

        feedback.FormClosed += (_, _) => completion.TrySetResult((
            feedback.DialogResult == DialogResult.OK,
            feedback.Feedback,
            feedback.Comprehension));

        feedback.Show();

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
    /// Hook shutdown / restart / log off: tutup sesi sesegera mungkin (tulis lokal
    /// dulu supaya tidak hilang), lalu upaya terakhir lapor ke server maksimal
    /// 2 detik. Sisa antrean akan tersinkron saat aplikasi jalan kembali.
    /// Selalu mengembalikan kendali agar tidak pernah menghambat shutdown.
    /// </summary>
    private void OnSystemSessionEnding(object sender, SessionEndingEventArgs e)
    {
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
            // Jejak diagnostik ringan (berguna saat maintenance).
            try
            {
                File.AppendAllText(
                    Path.Combine(_services.DataDirectory, "shutdown.log"),
                    $"{DateTime.Now:O} sesi={_record.SessionUuid} alasan=shutdown{Environment.NewLine}");
            }
            catch (Exception)
            {
                // Logging opsional.
            }

            StopTimers();

            if (_record.IsOpen)
            {
                _services.Sessions.Close(_record, null, null, "shutdown");

                try
                {
                    _services.Sessions.TryRemoteEndAsync(_record).Wait(TimeSpan.FromSeconds(2));
                }
                catch (Exception)
                {
                    // Diabaikan — antrean lokal akan menyusul saat aplikasi jalan lagi.
                }
            }

            _allowClose = true;
        }
        catch (Exception)
        {
            // Apa pun yang terjadi, jangan halangi shutdown.
        }
    }

    private void ShutdownRuntime()
    {
        StopTimers();
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

        base.OnFormClosing(e);
    }
}
