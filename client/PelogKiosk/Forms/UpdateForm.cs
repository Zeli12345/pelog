using System.Diagnostics;
using PelogKiosk.App.Services;

namespace PelogKiosk.App.Forms;

/// <summary>
/// Layar penuh yang memblokir login/enrollment sampai pembaruan terpasang.
/// Form ini sengaja tidak bisa ditutup; jalur darurat hanya Mode Admin.
/// </summary>
public sealed class UpdateForm : Form
{
    private readonly AppServices _services;

    private readonly Label _status;
    private readonly Label _version;
    private readonly Label _detail;
    private readonly Button _retry;
    private readonly Button _restart;
    private readonly System.Windows.Forms.Timer _pollTimer = new() { Interval = 2000 };

    private bool _allowClose;
    private bool _polling;

    public UpdateForm(AppServices services, PelogKiosk.Core.Services.UpdateGateDecision decision)
    {
        _services = services;

        Text = "PELOG — Pembaruan Sistem";
        FormBorderStyle = FormBorderStyle.None;
        StartPosition = FormStartPosition.CenterScreen;
        WindowState = FormWindowState.Maximized;
        TopMost = true;
        BackColor = Color.FromArgb(15, 34, 55);
        ForeColor = Color.White;
        Font = new Font("Segoe UI", 11F);
        ControlBox = false;

        var title = new Label
        {
            Text = "Memperbarui sistem PELOG",
            Font = new Font("Segoe UI", 26F, FontStyle.Bold),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Top,
            Height = 120,
        };

        _version = new Label
        {
            Text = decision.TargetVersion is null ? "Menyiapkan pembaruan…" : $"Versi tujuan: {decision.TargetVersion}",
            Font = new Font("Segoe UI", 14F),
            ForeColor = Color.FromArgb(201, 162, 39),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Top,
            Height = 50,
        };

        _status = new Label
        {
            Text = "Menunggu installer…",
            Font = new Font("Segoe UI", 13F),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Top,
            Height = 60,
        };

        var progress = new ProgressBar
        {
            Style = ProgressBarStyle.Marquee,
            MarqueeAnimationSpeed = 30,
            Width = 460,
            Height = 18,
            Top = 360,
            Left = 0,
        };

        _detail = new Label
        {
            Text = decision.Message ?? "Jangan matikan komputer. Kiosk akan terbuka otomatis setelah selesai.",
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(180, 200, 220),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Top,
            Height = 80,
        };

        _retry = new Button
        {
            Text = "Coba Lagi",
            Width = 140,
            Height = 40,
            BackColor = Color.FromArgb(35, 60, 88),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Visible = decision.Mode is "failed" or "staged",
        };
        _retry.Click += async (_, _) => await RefreshAsync();

        _restart = new Button
        {
            Text = "Restart Sekarang",
            Width = 160,
            Height = 40,
            BackColor = Color.FromArgb(201, 162, 39),
            ForeColor = Color.FromArgb(20, 26, 34),
            FlatStyle = FlatStyle.Flat,
            Visible = decision.State == "deferred",
        };
        _restart.Click += (_, _) => RestartComputer();

        var admin = new Button
        {
            Text = "Mode Admin",
            Width = 140,
            Height = 40,
            BackColor = Color.FromArgb(45, 50, 58),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
        };
        admin.Click += (_, _) => OpenAdminMode();

        var panel = new FlowLayoutPanel
        {
            FlowDirection = FlowDirection.LeftToRight,
            AutoSize = true,
            WrapContents = false,
            Anchor = AnchorStyles.None,
        };
        panel.Controls.AddRange([_retry, _restart, admin]);

        var layout = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 4,
            Padding = new Padding(40),
        };
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 35));
        layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        layout.RowStyles.Add(new RowStyle(SizeType.Percent, 65));
        layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));

        var center = new Panel { Dock = DockStyle.Fill };
        center.Controls.AddRange([title, _version, _status, _detail, progress, panel]);
        center.Resize += (_, _) =>
        {
            title.Width = center.Width;
            _version.Width = center.Width;
            _status.Width = center.Width;
            _detail.Width = center.Width;
            progress.Left = Math.Max(0, (center.Width - progress.Width) / 2);
            progress.Top = 380;
            panel.Left = Math.Max(0, (center.Width - panel.PreferredSize.Width) / 2);
            panel.Top = 440;
        };

        layout.Controls.Add(center, 0, 1);
        Controls.Add(layout);

        _pollTimer.Tick += async (_, _) => await RefreshAsync();
        Shown += async (_, _) => await RefreshAsync();
        _pollTimer.Start();
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (!_allowClose)
        {
            e.Cancel = true;
        }

        base.OnFormClosing(e);
    }

    private async Task RefreshAsync()
    {
        if (_polling)
        {
            return;
        }

        _polling = true;

        try
        {
            using var timeout = new CancellationTokenSource(TimeSpan.FromSeconds(5));
            var decision = await PelogKiosk.Core.Services.UpdateGate.EvaluateAsync(_services.Api, _services.DataDirectory, timeout.Token);

            if (!decision.Block)
            {
                _status.Text = "Pembaruan selesai. Membuka kiosk…";
                _pollTimer.Stop();
                await Task.Delay(700);
                RestartKiosk();

                return;
            }

            _version.Text = decision.TargetVersion is null
                ? "Menyiapkan pembaruan…"
                : $"Versi tujuan: {decision.TargetVersion}";
            _status.Text = decision.State switch
            {
                "checking" => "Memeriksa pembaruan…",
                "downloading" => "Mengunduh installer…",
                "installing" => "Installer sedang berjalan…",
                "deferred" => "Menunggu sesi sebelumnya selesai.",
                "failed" or "failed_permanent" => "Pembaruan gagal.",
                "waiting" => "Menunggu installer dari server…",
                _ => "Menyiapkan pembaruan…",
            };
            _detail.Text = decision.Message ?? "Jangan matikan komputer. Kiosk akan terbuka otomatis setelah selesai.";
            _retry.Visible = decision.Mode is "failed" or "staged" || decision.Attempts > 0;
            _restart.Visible = decision.State is "deferred" or "installed_pending_reboot" or "failed_retry" or "failed_permanent";
        }
        catch (Exception)
        {
            _status.Text = "Menunggu koneksi ke agen pembaruan…";
        }
        finally
        {
            _polling = false;
        }
    }

    private void RestartKiosk()
    {
        _allowClose = true;

        try
        {
            var arguments = $"/c timeout /t 3 >nul & start \"\" \"{Application.ExecutablePath}\"";
            Process.Start(new ProcessStartInfo("cmd.exe", arguments)
            {
                CreateNoWindow = true,
                UseShellExecute = false,
            });
        }
        catch (Exception)
        {
            // Watchdog/logon task akan menjalankan kiosk kembali.
        }

        Environment.Exit(0);
    }

    private void RestartComputer()
    {
        _allowClose = true;

        try
        {
            Process.Start(new ProcessStartInfo("shutdown", "/r /t 5 /c \"Menyelesaikan pembaruan PELOG\"") { CreateNoWindow = true, UseShellExecute = false });
        }
        catch (Exception)
        {
            _status.Text = "Restart otomatis gagal. Restart komputer secara manual.";
        }
    }

    private void OpenAdminMode()
    {
        using var unlock = new AdminUnlockForm(_services);

        if (unlock.ShowDialog() != DialogResult.OK)
        {
            return;
        }

        KioskHardening.EnsurePoliciesRemoved();
        _allowClose = true;
        Close();
    }
}
