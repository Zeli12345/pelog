using BalilogKiosk.App.Services;
using BalilogKiosk.Core.Data;
using Microsoft.Win32;
using Timer = System.Windows.Forms.Timer;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Runtime sesi aktif TANPA jendela.
/// Mengelola heartbeat, sinkronisasi, screenshot terjadwal, dan pengakhiran sesi
/// melalui ikon di system tray atau hotkey Ctrl+Alt+S.
/// </summary>
public sealed class ActiveSessionRuntime : Form
{
    private const int HotkeyId = 0xB1;
    private const int WmHotkey = 0x0312;
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

    private readonly NotifyIcon _tray;

    private readonly Timer _uiTimer = new() { Interval = 1000 };

    private readonly Timer _heartbeatTimer = new() { Interval = 60_000 };

    private readonly Timer _syncTimer = new() { Interval = 30_000 };

    private readonly Timer? _screenshotTimer;

    private bool _screenshotTaken;

    private bool _allowClose;

    private bool _finishing;

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

        // Form tidak pernah ditampilkan — hanya wadah handle untuk timer/tray/hotkey.
        ShowInTaskbar = false;
        FormBorderStyle = FormBorderStyle.None;
        WindowState = FormWindowState.Minimized;
        StartPosition = FormStartPosition.Manual;
        Location = new Point(-32000, -32000);
        Size = new Size(1, 1);

        _tray = new NotifyIcon
        {
            Icon = LoadTrayIcon(),
            Visible = true,
        };

        _tray.DoubleClick += async (_, _) => await EndSessionAsync();

        var menu = new ContextMenuStrip();
        menu.Items.Add(new ToolStripMenuItem("Selesai Penggunaan (Ctrl+Alt+S)", null, async (_, _) => await EndSessionAsync()));
        menu.Items.Add(new ToolStripSeparator());

        var infoItem = new ToolStripMenuItem($"{displayName} — {(string.IsNullOrEmpty(subjectName) ? subtitle : subjectName)}", null, (_, _) => { })
        {
            Enabled = false,
        };

        menu.Items.Add(infoItem);
        _tray.ContextMenuStrip = menu;

        var config = _services.Sync.LoadCachedConfig();
        var minute = _services.Config.TestMode ? _services.Config.TestScreenshotMinute : config.ScreenshotMinute;

        if (_isStudent && config.ScreenshotEnabled && minute > 0)
        {
            _screenshotTimer = new Timer { Interval = Math.Max(1, minute) * 60_000 };
            _screenshotTimer.Tick += OnScreenshotTick;
            _screenshotTimer.Start();
        }

        _uiTimer.Tick += (_, _) => UpdateTooltip();
        _heartbeatTimer.Tick += OnHeartbeatTick;
        _syncTimer.Tick += OnSyncTick;

        _uiTimer.Start();
        _heartbeatTimer.Start();
        _syncTimer.Start();

        SystemEvents.SessionEnding += OnSystemSessionEnding;

        UpdateTooltip();

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

        base.WndProc(ref m);
    }

    private static Icon LoadTrayIcon()
    {
        try
        {
            var logoPath = Path.Combine(AppContext.BaseDirectory, "Assets", "logo_sekolah.png");

            if (File.Exists(logoPath))
            {
                using var bitmap = new Bitmap(logoPath);
                using var resized = new Bitmap(bitmap, new Size(16, 16));

                return Icon.FromHandle(resized.GetHicon());
            }
        }
        catch (Exception)
        {
            // jatuh ke ikon bawaan
        }

        return SystemIcons.Application;
    }

    private TimeSpan Elapsed()
    {
        var started = _record.StartedAtClient ?? _services.Clock.Now;
        var elapsed = _services.Clock.Now - started;

        return elapsed < TimeSpan.Zero ? TimeSpan.Zero : elapsed;
    }

    private void UpdateTooltip()
    {
        var text = $"BALI-LOG • {Elapsed():hh\\:mm\\:ss} • {_displayName}";

        // Batas NotifyIcon.Text adalah 63 karakter.
        _tray.Text = text.Length > 60 ? text[..60] : text;
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

    /// <summary>Mengakhiri sesi (dipanggil tray / hotkey).</summary>
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
            using var feedback = new FeedbackForm(_displayName);

            if (feedback.ShowDialog() != DialogResult.OK)
            {
                _finishing = false;
                StartTimers();

                return;
            }

            _services.Sessions.Close(_record, feedback.Feedback, feedback.Comprehension);
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

    private void StartTimers()
    {
        _uiTimer.Start();
        _heartbeatTimer.Start();
        _syncTimer.Start();
        _screenshotTimer?.Start();
    }

    private void StopTimers()
    {
        _uiTimer.Stop();
        _heartbeatTimer.Stop();
        _syncTimer.Stop();
        _screenshotTimer?.Stop();
    }

    private void OnSystemSessionEnding(object sender, SessionEndingEventArgs e)
    {
        StopTimers();

        if (_record.IsOpen)
        {
            _services.Sessions.Close(_record, null, null, "shutdown");
        }

        _allowClose = true;
    }

    private void ShutdownRuntime()
    {
        StopTimers();
        _allowClose = true;

        SystemEvents.SessionEnding -= OnSystemSessionEnding;

        _tray.Visible = false;
        _tray.Dispose();

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
