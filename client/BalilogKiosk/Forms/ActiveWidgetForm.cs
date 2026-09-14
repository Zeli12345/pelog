using BalilogKiosk.App.Services;
using BalilogKiosk.Core.Data;
using Microsoft.Win32;
using Timer = System.Windows.Forms.Timer;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Widget sesi aktif: stopwatch, heartbeat, sinkronisasi, dan screenshot terjadwal.
/// Selalu di atas; siswa menekan SELESAI untuk mengakhiri sesi.
/// </summary>
public sealed class ActiveWidgetForm : Form
{
    private readonly AppServices _services;

    private readonly LocalSessionRecord _record;

    private readonly string _displayName;

    private readonly bool _isStudent;

    private readonly Label _timerLabel;

    private readonly Label _statusLabel;

    private readonly Button _finishButton;

    private readonly Timer _uiTimer = new() { Interval = 1000 };

    private readonly Timer _heartbeatTimer = new() { Interval = 60_000 };

    private readonly Timer _syncTimer = new() { Interval = 30_000 };

    private readonly Timer? _screenshotTimer;

    private bool _screenshotTaken;

    private bool _allowClose;

    private bool _finishing;

    public ActiveWidgetForm(
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

        Text = "BALI-LOG — Sesi Aktif";
        FormBorderStyle = FormBorderStyle.None;
        StartPosition = FormStartPosition.Manual;
        TopMost = true;
        ShowInTaskbar = true;
        BackColor = Color.FromArgb(15, 34, 55);
        ClientSize = new Size(340, 172);
        Font = new Font("Segoe UI", 9F);

        var workingArea = Screen.PrimaryScreen?.WorkingArea ?? new Rectangle(0, 0, 1280, 720);
        Location = new Point(workingArea.Right - Width - 14, workingArea.Bottom - Height - 14);

        // Bilah emas di kiri
        var accent = new Panel
        {
            BackColor = Color.FromArgb(201, 162, 39),
            Dock = DockStyle.Left,
            Width = 5,
        };

        var deviceLabel = new Label
        {
            Text = Environment.MachineName,
            Font = new Font("Segoe UI", 8.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(150, 175, 200),
            Location = new Point(20, 12),
            AutoSize = true,
        };

        var nameLabel = new Label
        {
            Text = displayName,
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            ForeColor = Color.White,
            Location = new Point(20, 32),
            AutoSize = false,
            Size = new Size(300, 22),
            AutoEllipsis = true,
        };

        var subtitleLabel = new Label
        {
            Text = string.IsNullOrEmpty(subjectName) ? subtitle : $"{subtitle} · {subjectName}",
            Font = new Font("Segoe UI", 8.5F),
            ForeColor = Color.FromArgb(180, 200, 220),
            Location = new Point(20, 56),
            AutoSize = false,
            Size = new Size(300, 18),
            AutoEllipsis = true,
        };

        _timerLabel = new Label
        {
            Text = "00:00:00",
            Font = new Font("Consolas", 20F, FontStyle.Bold),
            ForeColor = Color.FromArgb(255, 214, 120),
            Location = new Point(20, 78),
            AutoSize = true,
        };

        _statusLabel = new Label
        {
            Text = "• online",
            Font = new Font("Segoe UI", 8F),
            ForeColor = Color.FromArgb(150, 220, 170),
            Location = new Point(228, 92),
            AutoSize = true,
        };

        _finishButton = new Button
        {
            Text = "SELESAI PENGGUNAAN",
            Location = new Point(20, 122),
            Size = new Size(300, 36),
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            BackColor = Color.FromArgb(178, 58, 46),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        _finishButton.FlatAppearance.BorderSize = 0;
        _finishButton.Click += OnFinishClick;

        Controls.AddRange([accent, deviceLabel, nameLabel, subtitleLabel, _timerLabel, _statusLabel, _finishButton]);

        // Timer screenshot: satu kali di menit yang dikonfigurasi.
        var config = _services.Sync.LoadCachedConfig();
        var minute = _services.Config.TestMode ? _services.Config.TestScreenshotMinute : config.ScreenshotMinute;

        if (_isStudent && config.ScreenshotEnabled && minute > 0)
        {
            _screenshotTimer = new Timer { Interval = Math.Max(1, minute) * 60_000 };
            _screenshotTimer.Tick += OnScreenshotTick;
            _screenshotTimer.Start();
        }

        _uiTimer.Tick += (_, _) => UpdateElapsed();
        _heartbeatTimer.Tick += OnHeartbeatTick;
        _syncTimer.Tick += OnSyncTick;

        _uiTimer.Start();
        _heartbeatTimer.Start();
        _syncTimer.Start();

        SystemEvents.SessionEnding += OnSystemSessionEnding;

        UpdateElapsed();
    }

    private void UpdateElapsed()
    {
        var started = _record.StartedAtClient ?? _services.Clock.Now;
        var elapsed = _services.Clock.Now - started;

        if (elapsed < TimeSpan.Zero)
        {
            elapsed = TimeSpan.Zero;
        }

        _timerLabel.Text = $"{(int)elapsed.TotalHours:00}:{elapsed.Minutes:00}:{elapsed.Seconds:00}";
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

            ShutdownWidget();
        }

        UpdateStatus(data is not null);
    }

    private async void OnSyncTick(object? sender, EventArgs e)
    {
        var online = await _services.Api.HealthAsync();
        UpdateStatus(online);

        if (!online)
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

    private void UpdateStatus(bool online)
    {
        _statusLabel.Text = online ? "• online" : "• offline";
        _statusLabel.ForeColor = online
            ? Color.FromArgb(150, 220, 170)
            : Color.FromArgb(255, 190, 110);
    }

    private async void OnFinishClick(object? sender, EventArgs e)
    {
        if (_finishing)
        {
            return;
        }

        _finishing = true;
        _finishButton.Enabled = false;

        StopTimers();

        if (_isStudent)
        {
            using var feedback = new FeedbackForm(_displayName);

            if (feedback.ShowDialog(this) != DialogResult.OK)
            {
                _finishing = false;
                _finishButton.Enabled = true;
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

        ShutdownWidget();
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

    private void ShutdownWidget()
    {
        StopTimers();
        _allowClose = true;

        SystemEvents.SessionEnding -= OnSystemSessionEnding;

        Close();
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
