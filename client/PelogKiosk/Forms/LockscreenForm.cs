using System.Globalization;
using System.Text.RegularExpressions;
using PelogKiosk.App.Services;
using PelogKiosk.Core.Models;
using PelogKiosk.Core.Services;
using Timer = System.Windows.Forms.Timer;

namespace PelogKiosk.App.Forms;

/// <summary>
/// Layar kunci kiosk PELOG.
/// Alur siswa: NISN -> tanggal lahir -> mapel & tujuan -> sesi dimulai.
/// Alur guru/pegawai: NIP -> mapel & tujuan -> sesi dimulai.
/// </summary>
public sealed class LockscreenForm : Form
{
    private enum Mode
    {
        Student,
        Staff,
    }

    private enum Step
    {
        Identify,
        BirthDate,
        Details,
    }

    private const int MaxBirthDateAttempts = 5;

    private static readonly Color NavyDark = Color.FromArgb(15, 34, 55);
    private static readonly Color Navy = Color.FromArgb(27, 58, 92);
    private static readonly Color Gold = Color.FromArgb(201, 162, 39);
    private static readonly Color Paper = Color.FromArgb(247, 245, 241);
    private static readonly Color Ink = Color.FromArgb(25, 28, 32);
    private static readonly Color InkSoft = Color.FromArgb(90, 96, 105);
    private static readonly Color Brick = Color.FromArgb(178, 58, 46);
    private static readonly Color Moss = Color.FromArgb(47, 125, 92);

    private readonly AppServices _services;
    private readonly CultureInfo _culture = new("id-ID");

    private readonly Label _clockLabel;
    private readonly Label _serverLabel;
    private readonly Button _wifiButton;

    private readonly Button _shutdownButton;
    private WifiForm? _wifiDialog;

    private readonly Button _studentModeButton;
    private readonly Button _staffModeButton;

    private readonly Panel _card;

    private readonly Panel _identifyPanel;
    private readonly Label _identifyTitle;
    private readonly Label _identifyHint;
    private readonly TextBox _identifyInput;
    private readonly Label _identifyError;

    private readonly Panel _birthDatePanel;
    private readonly Label _birthDateName;
    private readonly TextBox _birthDateInput;
    private readonly Label _birthDateError;

    private readonly Panel _detailsPanel;
    private readonly Label _detailsName;
    private readonly Label _subjectLabel;
    private readonly ComboBox _subjectCombo;
    private readonly Label _purposeLabel;
    private readonly TextBox _purposeInput;
    private readonly Label _detailsError;

    private readonly Timer _clockTimer;
    private readonly Timer _serverTimer;
    private readonly Timer _syncTimer;

    private readonly Timer _idleTimer;

    private Mode _mode = Mode.Student;
    private CachedStudent? _student;
    private CachedStaff? _staff;
    private readonly KeyboardBlocker _keyboardBlocker = new();
    private bool _allowExit;
    private bool _busy;
    private bool _enrollmentRecoveryOpen;
    private bool _revokedHandled;
    private int _birthDateAttempts;
    private Task _startupHardening = Task.CompletedTask;

    public LockscreenForm(AppServices services)
    {
        _services = services;

        // Pastikan kebijakan penguncian terpasang setiap aplikasi dijalankan
        // (mis. setelah "Keluar Aplikasi" lalu pengawas menghidupkan ulang kiosk).
        // Task disimpan agar sesi yang dipulihkan tidak balapan dengan Suspend.
        if (_services.Config.HardeningEnabled && !_services.Config.TestMode)
        {
            _startupHardening = Task.Run(KioskHardening.Apply);
        }

        Text = "PELOG — Kiosk";
        WindowState = FormWindowState.Maximized;
        FormBorderStyle = FormBorderStyle.None;
        TopMost = true;
        BackColor = NavyDark;
        KeyPreview = true;
        Font = new Font("Segoe UI", 10F);

        // ---------- Header ----------
        var header = new Panel { Dock = DockStyle.Top, Height = 92, BackColor = NavyDark };

        var logo = new PictureBox
        {
            Image = LoadLogo(),
            SizeMode = PictureBoxSizeMode.Zoom,
            Size = new Size(56, 56),
            Location = new Point(36, 18),
            BackColor = Color.Transparent,
        };

        var title = new Label
        {
            Text = "PELOG",
            Font = new Font("Segoe UI", 17F, FontStyle.Bold),
            ForeColor = Color.White,
            Location = new Point(104, 22),
            AutoSize = true,
        };

        var schoolName = new Label
        {
            Text = _services.Sync.LoadCachedConfig().SchoolName,
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = Color.FromArgb(150, 175, 200),
            Location = new Point(106, 52),
            AutoSize = true,
        };

        _clockLabel = new Label
        {
            Font = new Font("Consolas", 12F, FontStyle.Bold),
            ForeColor = Color.FromArgb(255, 214, 120),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleRight,
            Size = new Size(420, 44),
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
        };

        header.Controls.AddRange([logo, title, schoolName, _clockLabel]);
        header.Resize += (_, _) => _clockLabel.Location = new Point(header.Width - 456, 26);

        // ---------- Footer ----------
        var footer = new Panel { Dock = DockStyle.Bottom, Height = 40, BackColor = Color.FromArgb(12, 28, 46) };

        _serverLabel = new Label
        {
            Text = "• memeriksa server…",
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(180, 200, 220),
            Location = new Point(36, 10),
            AutoSize = true,
        };

        var deviceLabel = new Label
        {
            Text = $"Perangkat: {Environment.MachineName}",
            Font = new Font("Consolas", 9F),
            ForeColor = Color.FromArgb(150, 175, 200),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleRight,
            Size = new Size(420, 20),
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
        };

        // Tombol Wi-Fi: agar perangkat dapat disambungkan ke jaringan sekolah
        // langsung dari layar kunci, tanpa keluar dari kiosk.
        _wifiButton = new Button
        {
            Text = "📶  Wi-Fi",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.White,
            BackColor = Navy,
            FlatStyle = FlatStyle.Flat,
            Size = new Size(118, 28),
            Location = new Point(600, 6),
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
            Cursor = Cursors.Hand,
            TabStop = false,
        };

        _wifiButton.FlatAppearance.BorderSize = 1;
        _wifiButton.FlatAppearance.BorderColor = Gold;
        _wifiButton.Click += (_, _) => OpenWifiDialog();

        // Tombol Matikan: mematikan laptop langsung dari layar kunci (dengan konfirmasi).
        _shutdownButton = new Button
        {
            Text = "⏻  Matikan",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.White,
            BackColor = Navy,
            FlatStyle = FlatStyle.Flat,
            Size = new Size(118, 28),
            Location = new Point(472, 6),
            Anchor = AnchorStyles.Top | AnchorStyles.Right,
            Cursor = Cursors.Hand,
            TabStop = false,
        };

        _shutdownButton.FlatAppearance.BorderSize = 1;
        _shutdownButton.FlatAppearance.BorderColor = Color.FromArgb(200, 96, 86);
        _shutdownButton.Click += (_, _) => ConfirmShutdown();

        footer.Controls.AddRange([_serverLabel, deviceLabel, _wifiButton, _shutdownButton]);
        footer.Resize += (_, _) =>
        {
            _shutdownButton.Location = new Point(footer.Width - 724, 6);
            _wifiButton.Location = new Point(footer.Width - 596, 6);
            deviceLabel.Location = new Point(footer.Width - 456, 10);
        };

        // ---------- Mode toggle ----------
        _studentModeButton = CreateModeButton("Siswa");
        _studentModeButton.Click += (_, _) => SetMode(Mode.Student);

        _staffModeButton = CreateModeButton("Guru / Pegawai");
        _staffModeButton.Click += (_, _) => SetMode(Mode.Staff);

        // ---------- Kartu tengah ----------
        _card = new Panel
        {
            Size = new Size(640, 470),
            BackColor = Paper,
            Anchor = AnchorStyles.None,
        };

        var cardHeader = new Label
        {
            Text = "SISTEM MONITORING LAPTOP LAB",
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            ForeColor = Navy,
            Location = new Point(36, 26),
            AutoSize = true,
        };

        var cardSubtitle = new Label
        {
            Text = "Isi identitas untuk mulai menggunakan perangkat.",
            Font = new Font("Segoe UI", 9F),
            ForeColor = InkSoft,
            Location = new Point(38, 52),
            AutoSize = true,
        };

        var separator = new Panel { BackColor = Color.FromArgb(228, 223, 214), Location = new Point(36, 84), Size = new Size(568, 1) };

        _studentModeButton.Location = new Point(36, 100);
        _staffModeButton.Location = new Point(212, 100);

        // ---------- Panel: Identifikasi ----------
        _identifyPanel = new Panel { Location = new Point(36, 156), Size = new Size(568, 290), BackColor = Color.Transparent };

        _identifyTitle = new Label
        {
            Text = "Nomor Induk Siswa Nasional (NISN)",
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            ForeColor = Ink,
            Location = new Point(2, 0),
            AutoSize = true,
        };

        _identifyHint = new Label
        {
            Text = "10 angka · nama akan muncul otomatis",
            Font = new Font("Segoe UI", 8.5F),
            ForeColor = InkSoft,
            Location = new Point(4, 24),
            AutoSize = true,
        };

        _identifyInput = new TextBox
        {
            Location = new Point(2, 52),
            Width = 420,
            Font = new Font("Consolas", 20F, FontStyle.Bold),
            MaxLength = 10,
            CharacterCasing = CharacterCasing.Upper,
            BorderStyle = BorderStyle.FixedSingle,
        };

        _identifyInput.KeyPress += OnlyDigits;

        var identifyButton = new Button
        {
            Text = "L A N J U T",
            Location = new Point(436, 52),
            Size = new Size(130, 44),
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            BackColor = Navy,
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        identifyButton.FlatAppearance.BorderSize = 0;
        identifyButton.Click += async (_, _) => await IdentifyAsync();

        _identifyError = new Label
        {
            Location = new Point(2, 110),
            Size = new Size(564, 60),
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = Brick,
            AutoSize = false,
        };

        _identifyPanel.Controls.AddRange([_identifyTitle, _identifyHint, _identifyInput, identifyButton, _identifyError]);

        // ---------- Panel: Tanggal lahir ----------
        _birthDatePanel = new Panel { Location = new Point(36, 156), Size = new Size(568, 290), BackColor = Color.Transparent, Visible = false };

        _birthDateName = new Label
        {
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Navy,
            Location = new Point(2, 0),
            AutoSize = true,
        };

        var birthDateHint = new Label
        {
            Text = "Masukkan tanggal lahirmu sesuai data sekolah.",
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = InkSoft,
            Location = new Point(4, 30),
            AutoSize = true,
        };

        var birthDateLabel = new Label
        {
            Text = "Tanggal lahir (DD-MM-YYYY)",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Ink,
            Location = new Point(2, 62),
            AutoSize = true,
        };

        _birthDateInput = new TextBox
        {
            Location = new Point(2, 86),
            Width = 240,
            Font = new Font("Consolas", 20F, FontStyle.Bold),
            MaxLength = 10,
            BorderStyle = BorderStyle.FixedSingle,
            PlaceholderText = "31-12-2008",
        };

        _birthDateInput.KeyDown += (_, e) =>
        {
            if (e.KeyCode == Keys.Enter)
            {
                e.SuppressKeyPress = true;
                SubmitBirthDate();
            }
        };

        var birthDateButton = new Button
        {
            Text = "L A N J U T",
            Location = new Point(2, 142),
            Size = new Size(428, 46),
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            BackColor = Navy,
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        birthDateButton.FlatAppearance.BorderSize = 0;
        birthDateButton.Click += (_, _) => SubmitBirthDate();

        var birthDateBack = CreateBackButton();
        birthDateBack.Location = new Point(440, 142);
        birthDateBack.Click += (_, _) => ResetFlow();

        var birthDateFormatHint = new Label
        {
            Text = "Format: 31-12-2008, 31/12/2008, atau 2008-12-31.",
            Font = new Font("Segoe UI", 8.5F),
            ForeColor = InkSoft,
            Location = new Point(4, 196),
            AutoSize = true,
        };

        _birthDateError = new Label
        {
            Location = new Point(2, 222),
            Size = new Size(564, 60),
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = Brick,
            AutoSize = false,
        };

        _birthDatePanel.Controls.AddRange([
            _birthDateName, birthDateHint, birthDateLabel, _birthDateInput,
            birthDateButton, birthDateBack, birthDateFormatHint, _birthDateError,
        ]);

        // ---------- Panel: Detail sesi ----------
        _detailsPanel = new Panel { Location = new Point(36, 156), Size = new Size(568, 290), BackColor = Color.Transparent, Visible = false };

        _detailsName = new Label
        {
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Navy,
            Location = new Point(2, 0),
            AutoSize = true,
        };

        _subjectLabel = new Label { Text = "Mata Pelajaran", Font = new Font("Segoe UI", 9F, FontStyle.Bold), ForeColor = Ink, Location = new Point(2, 36), AutoSize = true };

        _subjectCombo = new ComboBox
        {
            Location = new Point(2, 58),
            Width = 360,
            DropDownStyle = ComboBoxStyle.DropDownList,
            Font = new Font("Segoe UI", 10F),
        };

        _purposeLabel = new Label { Text = "Rencana / tujuan penggunaan", Font = new Font("Segoe UI", 9F, FontStyle.Bold), ForeColor = Ink, Location = new Point(2, 100), AutoSize = true };

        _purposeInput = new TextBox
        {
            Location = new Point(2, 122),
            Size = new Size(562, 74),
            Multiline = true,
            Font = new Font("Segoe UI", 10.5F),
            BorderStyle = BorderStyle.FixedSingle,
            MaxLength = 500,
        };

        var startButton = new Button
        {
            Text = "🔓  MULAI GUNAKAN LAPTOP",
            Location = new Point(2, 208),
            Size = new Size(428, 48),
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            BackColor = Moss,
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        startButton.FlatAppearance.BorderSize = 0;
        startButton.Click += async (_, _) => await StartSessionAsync();

        var detailsBack = CreateBackButton();
        detailsBack.Location = new Point(440, 208);
        detailsBack.Click += (_, _) => ResetFlow();

        _detailsError = new Label
        {
            Location = new Point(2, 260),
            Size = new Size(564, 30),
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = Brick,
            AutoSize = false,
        };

        _detailsPanel.Controls.AddRange([
            _detailsName, _subjectLabel, _subjectCombo, _purposeLabel, _purposeInput,
            startButton, detailsBack, _detailsError,
        ]);

        _card.Controls.AddRange([
            cardHeader, cardSubtitle, separator, _studentModeButton, _staffModeButton,
            _identifyPanel, _birthDatePanel, _detailsPanel,
        ]);

        var content = new Panel { Dock = DockStyle.Fill, BackColor = NavyDark };
        content.Controls.Add(_card);
        content.Resize += (_, _) => CenterCard();

        Controls.AddRange([content, header, footer]);

        // ---------- Timer ----------
        _clockTimer = new Timer { Interval = 1000 };
        _clockTimer.Tick += (_, _) => UpdateClock();
        _clockTimer.Start();

        _serverTimer = new Timer { Interval = 15_000 };
        _serverTimer.Tick += async (_, _) => await CheckServerAsync();
        _serverTimer.Start();

        _syncTimer = new Timer { Interval = 60_000 };
        _syncTimer.Tick += async (_, _) => await SyncIdleAsync();
        _syncTimer.Start();

        _idleTimer = new Timer { Interval = 60_000 };
        _idleTimer.Tick += (_, _) => CheckIdleShutdown();
        _idleTimer.Start();

        SetMode(Mode.Student);
        ShowStep(Step.Identify);

        Load += (_, _) =>
        {
            CenterCard();
            _identifyInput.Focus();
            ApplyKioskHardening();
            TryRecoverSession();
        };

        Resize += (_, _) => CenterCard();

        UpdateClock();
        _ = CheckServerAsync();
        _ = RefreshBootstrapQuietlyAsync();
    }

    private void CenterCard()
    {
        _card.Location = new Point(
            Math.Max(0, (ClientSize.Width - _card.Width) / 2),
            Math.Max(110, (ClientSize.Height - _card.Height) / 2 + 10));
    }

    private static Image? LoadLogo()
    {
        foreach (var candidate in new[]
        {
            Path.Combine(AppContext.BaseDirectory, "Assets", "logo_sekolah.png"),
            Path.Combine(AppContext.BaseDirectory, "logo_sekolah.png"),
        })
        {
            if (File.Exists(candidate))
            {
                using var stream = File.OpenRead(candidate);

                return Image.FromStream(stream);
            }
        }

        return null;
    }

    /// <summary>
    /// Dialog Wi-Fi di layar kunci, dipakai bila laptop belum tersambung ke
    /// jaringan sekolah (mis. setelah dipindah ruangan atau ganti SSID).
    /// </summary>
    private void OpenWifiDialog()
    {
        // Dialog TIDAK memakai ShowDialog: jendela kiosk selalu TopMost sehingga
        // dialog modal bisa kalah z-order / tidak tampil. Pakai Show() (non-modal)
        // + nonaktifkan jendela kiosk sendiri supaya efeknya tetap modal.
        if (_wifiDialog is not null)
        {
            _wifiDialog.Activate();

            return;
        }

        var dialog = new WifiForm(_services.Config.AllowedWifiSsids);

        _wifiDialog = dialog;
        Enabled = false;

        dialog.FormClosed += (_, _) =>
        {
            _wifiDialog = null;
            Enabled = true;
            Activate();
            BringToFront();
        };

        dialog.Show();
        dialog.Activate();
        dialog.BringToFront();
    }

    private Button CreateModeButton(string text)
    {
        var button = new Button
        {
            Text = text,
            Size = new Size(168, 40),
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
            BackColor = Color.White,
            ForeColor = InkSoft,
        };

        button.FlatAppearance.BorderSize = 1;
        button.FlatAppearance.BorderColor = Color.FromArgb(228, 223, 214);

        return button;
    }

    private static Button CreateBackButton()
    {
        var button = new Button
        {
            Text = "Kembali",
            Size = new Size(124, 46),
            Font = new Font("Segoe UI", 9.5F),
            BackColor = Color.White,
            ForeColor = InkSoft,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        button.FlatAppearance.BorderSize = 1;
        button.FlatAppearance.BorderColor = Color.FromArgb(203, 196, 192);

        return button;
    }

    private static void OnlyDigits(object? sender, KeyPressEventArgs e)
    {
        if (!char.IsControl(e.KeyChar) && !char.IsAsciiDigit(e.KeyChar))
        {
            e.Handled = true;
        }
    }

    private void UpdateClock()
    {
        _clockLabel.Text = _services.Clock.Now
            .ToOffset(TimeSpan.FromHours(8))
            .ToString("dddd, dd MMMM yyyy  ·  HH:mm:ss", _culture) + " WITA";
    }

    private async Task CheckServerAsync()
    {
        var online = await _services.Api.HealthAsync();

        if (_services.Store.GetKv("device_maintenance") == "1")
        {
            _serverLabel.Text = "• Laptop dalam perawatan Admin IT";
            _serverLabel.ForeColor = Color.FromArgb(255, 200, 110);

            return;
        }

        _serverLabel.Text = online ? "• Terhubung ke server" : "• Mode offline — data tersimpan di laptop";
        _serverLabel.ForeColor = online
            ? Color.FromArgb(150, 220, 170)
            : Color.FromArgb(255, 190, 110);
    }

    /// <summary>
    /// Sinkronisasi saat kiosk menganggur: kirim sesi/screenshot tertunda
    /// (hasil recovery atau mode offline) dan segarkan cache berkala.
    /// </summary>
    private async Task SyncIdleAsync()
    {
        if (await _services.Api.HealthAsync())
        {
            // Token yang dicabut server tidak boleh membuat kiosk offline
            // selamanya: token dihapus dan pendaftaran ulang ditawarkan.
            // Perangkat yang DIHAPUS dari dashboard (device_revoked) berbeda:
            // tidak ada pendaftaran ulang — jalur wipe total yang dipakai.
            var tokenStatus = await _services.VerifyDeviceTokenAsync();

            if (tokenStatus == DeviceTokenStatus.Revoked)
            {
                HandleDeviceRevoked("sinkronisasi layar kunci");

                return;
            }

            if (tokenStatus == DeviceTokenStatus.Invalid)
            {
                RecoverEnrollment();

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
    }

    /// <summary>
    /// Token perangkat ditolak server (device_token_invalid): buka dialog
    /// pendaftaran tanpa menutup kiosk supaya perangkat bisa pulih sendiri.
    /// Selalu dijalankan di UI thread (dialog WinForms).
    /// </summary>
    private void RecoverEnrollment()
    {
        if (IsDisposed || Disposing)
        {
            return;
        }

        if (InvokeRequired)
        {
            BeginInvoke(new Action(RecoverEnrollment));

            return;
        }

        if (_enrollmentRecoveryOpen)
        {
            return;
        }

        _enrollmentRecoveryOpen = true;

        // Sama seperti dialog Wi-Fi: jendela kiosk TopMost harus dilepas
        // sementara supaya dialog enrollment tampil di depan.
        var wasTopMost = TopMost;
        TopMost = false;

        try
        {
            using var enroll = new EnrollForm(_services);

            enroll.ShowDialog(this);
        }
        finally
        {
            TopMost = wasTopMost;
            Activate();
            _enrollmentRecoveryOpen = false;
        }
    }

    /// <summary>
    /// Perangkat dihapus dari dashboard (410 device_revoked): jalankan wipe total
    /// di UI thread, tanpa jalur pendaftaran ulang. Bila SelfWipeOnRevoke dimatikan,
    /// kejadian hanya dicatat sekali agar tidak membanjiri log tiap timer.
    /// </summary>
    private void HandleDeviceRevoked(string reason)
    {
        if (IsDisposed || Disposing || _revokedHandled)
        {
            return;
        }

        if (InvokeRequired)
        {
            BeginInvoke(new Action(() => HandleDeviceRevoked(reason)));

            return;
        }

        _revokedHandled = true;

        if (!_services.Config.SelfWipeOnRevoke)
        {
            LocalLog.Write(
                _services.DataDirectory,
                $"device_revoked ({reason}) — SelfWipeOnRevoke=false, wipe tidak dijalankan.");

            return;
        }

        SelfWipeService.Trigger($"layar kunci: {reason}");
    }

    private async Task RefreshBootstrapQuietlyAsync()
    {
        if (await _services.Api.HealthAsync())
        {
            await _services.Sync.RefreshBootstrapAsync();
        }
    }

    private void SetMode(Mode mode)
    {
        _mode = mode;

        _studentModeButton.BackColor = mode == Mode.Student ? Navy : Color.White;
        _studentModeButton.ForeColor = mode == Mode.Student ? Color.White : InkSoft;
        _staffModeButton.BackColor = mode == Mode.Staff ? Navy : Color.White;
        _staffModeButton.ForeColor = mode == Mode.Staff ? Color.White : InkSoft;

        _identifyTitle.Text = mode == Mode.Student
            ? "Nomor Induk Siswa Nasional (NISN)"
            : "NIP / NUPTK Guru atau Pegawai";

        _identifyHint.Text = mode == Mode.Student
            ? "10 angka · nama akan muncul otomatis"
            : "Masukkan NIP sesuai data sekolah";

        _identifyInput.MaxLength = mode == Mode.Student ? 10 : 30;
        _identifyInput.Clear();
        _identifyError.Text = string.Empty;
        _student = null;
        _staff = null;
        _birthDateAttempts = 0;
        _birthDateInput.Clear();
        _birthDateError.Text = string.Empty;

        ShowStep(Step.Identify);
    }

    private void ShowStep(Step step)
    {
        _identifyPanel.Visible = step == Step.Identify;
        _birthDatePanel.Visible = step == Step.BirthDate;
        _detailsPanel.Visible = step == Step.Details;

        switch (step)
        {
            case Step.Identify:
                _identifyInput.Focus();
                break;
            case Step.BirthDate:
                _birthDateInput.Clear();
                _birthDateError.Text = string.Empty;
                _birthDateInput.Focus();
                break;
            case Step.Details:
                _purposeInput.Clear();
                _detailsError.Text = string.Empty;
                _purposeInput.Focus();
                break;
        }
    }

    private async Task IdentifyAsync()
    {
        if (_busy)
        {
            return;
        }

        _busy = true;
        _identifyError.Text = string.Empty;

        try
        {
            var value = _identifyInput.Text.Trim();

            if (_mode == Mode.Student)
            {
                if (!Regex.IsMatch(value, @"^\d{10}$"))
                {
                    _identifyError.Text = "NISN terdiri dari 10 angka. Periksa kembali.";

                    return;
                }

                var student = _services.Store.GetStudent(value);

                if (student is null)
                {
                    if (await _services.Api.HealthAsync())
                    {
                        var result = await _services.Api.LookupStudentAsync(value);

                        if (result.Ok && result.Data is not null)
                        {
                            student = result.Data;
                        }
                        else if (result.ErrorCode == "student_inactive")
                        {
                            _identifyError.Text = "Akun ini dinonaktifkan. Lapor guru/IT.";

                            return;
                        }
                        else
                        {
                            _identifyError.Text = "NISN tidak terdaftar. Periksa kembali atau lapor guru/IT.";

                            return;
                        }
                    }
                    else
                    {
                        _identifyError.Text = "NISN tidak ditemukan di data laptop ini. Coba lagi saat koneksi tersambung, atau lapor guru/IT.";

                        return;
                    }
                }

                if (student.BirthDate is null)
                {
                    _student = null;
                    _identifyError.Text = "Data tanggal lahir belum ada - hubungi Admin IT";

                    return;
                }

                _student = student;
                _birthDateAttempts = 0;
                _birthDateName.Text = $"{student.Name} · {student.ClassName}";
                ShowStep(Step.BirthDate);
            }
            else
            {
                if (value.Length < 8)
                {
                    _identifyError.Text = "NIP minimal 8 karakter. Periksa kembali.";

                    return;
                }

                var staff = _services.Store.GetStaff(value);

                if (staff is null && await _services.Api.HealthAsync())
                {
                    await _services.Sync.RefreshBootstrapAsync();
                    staff = _services.Store.GetStaff(value);
                }

                if (staff is null)
                {
                    _identifyError.Text = "NIP tidak terdaftar atau data belum tersinkron. Lapor guru/IT.";

                    return;
                }

                _staff = staff;
                PrepareDetails();
            }
        }
        finally
        {
            _busy = false;
        }
    }

    /// <summary>
    /// Verifikasi tanggal lahir siswa. Maksimal 5 percobaan;
    /// bila melewati batas, siswa dikembalikan ke layar NISN.
    /// </summary>
    private void SubmitBirthDate()
    {
        if (_busy || _student is null)
        {
            return;
        }

        _busy = true;

        try
        {
            _birthDateError.Text = string.Empty;

            if (_student.BirthDate is null)
            {
                _student = null;
                ResetFlow();
                _identifyError.Text = "Data tanggal lahir belum ada - hubungi Admin IT";

                return;
            }

            if (!TryParseBirthDate(_birthDateInput.Text, out var submitted))
            {
                _birthDateError.Text = "Format tanggal tidak dikenali. Contoh: 31-12-2008.";
                _birthDateInput.Focus();
                _birthDateInput.SelectAll();

                return;
            }

            if (submitted != _student.BirthDate.Value)
            {
                _birthDateAttempts++;
                _birthDateInput.Clear();
                _birthDateInput.Focus();

                if (_birthDateAttempts >= MaxBirthDateAttempts)
                {
                    ResetFlow();
                    _identifyError.Text = $"Tanggal lahir salah {MaxBirthDateAttempts}×. Masukkan NISN kembali, atau hubungi Admin IT.";

                    return;
                }

                _birthDateError.Text = $"Tanggal lahir tidak cocok ({_birthDateAttempts}/{MaxBirthDateAttempts}). Coba lagi.";

                return;
            }

            _birthDateAttempts = 0;
            PrepareDetails();
        }
        finally
        {
            _busy = false;
        }
    }

    /// <summary>
    /// Mengurai tanggal lahir yang diterima: DD-MM-YYYY, DD/MM/YYYY, atau
    /// YYYY-MM-DD, lalu dinormalisasi menjadi <see cref="DateOnly"/> (ISO).
    /// </summary>
    private static bool TryParseBirthDate(string value, out DateOnly date)
    {
        date = default;

        var text = value.Trim().Replace('/', '-');

        if (text.Length == 0)
        {
            return false;
        }

        if (!DateTime.TryParseExact(
                text,
                ["yyyy-M-d", "d-M-yyyy"],
                CultureInfo.InvariantCulture,
                DateTimeStyles.None,
                out var parsed))
        {
            return false;
        }

        date = DateOnly.FromDateTime(parsed);

        return true;
    }

    private void PrepareDetails()
    {
        if (_mode == Mode.Student && _student is not null)
        {
            _detailsName.Text = $"{_student.Name} · {_student.ClassName}";

            _subjectLabel.Visible = true;
            _subjectCombo.Visible = true;
        }
        else if (_staff is not null)
        {
            _detailsName.Text = _staff.Name;

            _subjectLabel.Visible = false;
            _subjectCombo.Visible = false;
        }

        // Isi daftar mapel dari cache.
        _subjectCombo.Items.Clear();

        foreach (var subject in _services.Store.GetSubjects())
        {
            _subjectCombo.Items.Add(subject);
        }

        _subjectCombo.DisplayMember = nameof(CachedSubject.Name);

        ShowStep(Step.Details);
    }

    private async Task StartSessionAsync()
    {
        if (_busy)
        {
            return;
        }

        _busy = true;

        try
        {
            var purpose = _purposeInput.Text.Trim();

            if (purpose.Length < 3)
            {
                _detailsError.Text = "Tuliskan tujuan penggunaan minimal 3 karakter.";

                return;
            }

            if (_services.Store.GetKv("device_maintenance") == "1" &&
                !await _services.Api.HealthAsync())
            {
                // Saat offline, pakai status terakhir yang diketahui (konservatif).
                _detailsError.Text = "Laptop ini sedang dalam perawatan Admin IT.";

                return;
            }

            long? subjectId = null;

            if (_mode == Mode.Student)
            {
                if (_student is null)
                {
                    ResetFlow();

                    return;
                }

                if (_subjectCombo.SelectedItem is not CachedSubject subject)
                {
                    _detailsError.Text = "Pilih mata pelajaran terlebih dahulu.";

                    return;
                }

                subjectId = subject.Id;

                var record = _services.Sessions.BeginStudent(_student.Nisn, subjectId, purpose);

                if (!await StartRemoteOrRejectAsync(record))
                {
                    _detailsError.Text = "Laptop ini sedang dalam perawatan Admin IT. Penggunaan tidak dapat dimulai.";

                    return;
                }

                LaunchSession(record);
            }
            else
            {
                if (_staff is null)
                {
                    ResetFlow();

                    return;
                }

                var record = _services.Sessions.BeginStaff(_staff.NipId, purpose);

                if (!await StartRemoteOrRejectAsync(record))
                {
                    _detailsError.Text = "Laptop ini sedang dalam perawatan Admin IT. Penggunaan tidak dapat dimulai.";

                    return;
                }

                LaunchSession(record);
            }

            await Task.CompletedTask;
        }
        finally
        {
            _busy = false;
        }
    }

    /// <summary>
    /// Kirim start ke server saat online agar penolakan (mis. perangkat dalam
    /// perawatan) langsung terlihat. Saat offline, sesi tetap berjalan lokal.
    /// </summary>
    private async Task<bool> StartRemoteOrRejectAsync(Core.Data.LocalSessionRecord record)
    {
        if (!await _services.Api.HealthAsync())
        {
            _ = _services.Sessions.TryRemoteStartAsync(record);

            return true;
        }

        var result = await _services.Sessions.TryRemoteStartAsync(record);

        if (result is { Ok: false, ErrorCode: "device_maintenance" })
        {
            _services.Sessions.Close(record, null, null, "admin");
            _services.Store.SetKv("device_maintenance", "1");

            return false;
        }

        if (result is { Ok: true })
        {
            // Sesi diterima server: pastikan penanda perawatan lama dibersihkan.
            _services.Store.SetKv("device_maintenance", "0");
        }

        return true;
    }

    private void LaunchSession(Core.Data.LocalSessionRecord record)
    {
        Hide();

        // Sesi berjalan: desktop dipakai normal (Run, CMD, Pengaturan, dll. bebas).
        EnterSessionMode();

        // Runtime sesi berjalan tanpa jendela: hotkey Ctrl+Alt+S (cadangan Ctrl+Alt+E) untuk mengakhiri.
        var runtime = new ActiveSessionRuntime(_services, record);

        runtime.FormClosed += (_, _) =>
        {
            // Kembali ke layar kunci: pasang lagi penguncian kiosk.
            ExitSessionMode();

            ResetFlow();
            Show();
            Activate();
            _ = RefreshBootstrapQuietlyAsync();
        };
    }

    /// <summary>
    /// Mode sesi aktif: pemblokir keyboard dimatikan dan kebijakan kiosk
    /// ditangguhkan agar seluruh aplikasi (Run, CMD, Settings, dsb.)
    /// berfungsi normal selama siswa/guru memakai laptop.
    /// </summary>
    private void EnterSessionMode()
    {
        _keyboardBlocker.SetEnabled(false);

        if (!_services.Config.HardeningEnabled || _services.Config.TestMode)
        {
            return;
        }

        // Tunggu penerapan hardening saat startup selesai lebih dulu: bila tidak,
        // Apply yang masih berjalan dapat memasang kembali kebijakan tepat setelah
        // sesi dimulai sehingga CMD/Run/Task Manager ikut terkunci saat sesi aktif.
        var startupHardening = _startupHardening;

        _ = Task.Run(() =>
        {
            try
            {
                startupHardening.Wait();
            }
            catch (Exception)
            {
                // Kegagalan Apply tidak boleh menghalangi sesi.
            }

            KioskHardening.Suspend();
        });
    }

    /// <summary>
    /// Kembali ke layar kunci: pasang ulang pemblokir keyboard dan kebijakan
    /// kiosk supaya laptop tetap terkunci saat tidak ada sesi.
    /// </summary>
    private void ExitSessionMode()
    {
        _keyboardBlocker.SetEnabled(true);

        if (!_services.Config.HardeningEnabled || _services.Config.TestMode)
        {
            return;
        }

        Task.Run(KioskHardening.Apply);
    }

    private void ResetFlow()
    {
        _student = null;
        _staff = null;
        _birthDateAttempts = 0;

        _identifyInput.Clear();
        _identifyError.Text = string.Empty;
        _birthDateInput.Clear();
        _birthDateError.Text = string.Empty;
        _purposeInput.Clear();
        _detailsError.Text = string.Empty;
        _subjectCombo.Items.Clear();

        ShowStep(Step.Identify);
    }

    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool RegisterHotKey(IntPtr hWnd, int id, uint fsModifiers, uint vk);

    private const int WmHotkey = 0x0312;

    private void ApplyKioskHardening()
    {
        if (!_services.Config.HardeningEnabled || _services.Config.TestMode)
        {
            return;
        }

        // Kebijakan registry (Task Manager, CMD, Regedit, dll.) diterapkan oleh
        // INSTALLER dengan hak admin. Aplikasi kiosk (non-admin) hanya memasang
        // pemblokir shortcut yang berjalan di sesi pengguna.
        // Bila hook gagal, ulangi sekali lalu catat supaya kondisi kiosk yang
        // tidak terkunci (Alt+Tab/Win/Ctrl+Esc) tidak lewat begitu saja.
        if (!_keyboardBlocker.Install() && !_keyboardBlocker.Install())
        {
            LocalLog.Write(
                _services.DataDirectory,
                "keyboard-blocker: hook gagal dipasang — Alt+Tab/tombol Windows/Ctrl+Esc mungkin tidak terblokir.");
        }
    }

    /// <summary>
    /// Saat aplikasi dibuka: lanjutkan sesi yang masih berjalan, atau tutup sesi
    /// menggantung sebagai "recovery" (misal setelah mati listrik).
    /// </summary>
    private void TryRecoverSession()
    {
        var recovery = _services.Sessions.RecoverOnStartup();

        if (recovery.Action == RecoveryAction.None || recovery.Record is null)
        {
            return;
        }

        if (recovery.Action == RecoveryAction.ClosedAsRecovery)
        {
            // Sesi lama ditutup otomatis; worker akan menyinkronkannya.
            _ = _services.Sync.PushSessionsAsync();

            return;
        }

        // Sesi masih segar -> lanjutkan runtime sesi yang sama.
        BeginInvoke(() => LaunchSession(recovery.Record));
    }

    protected override void OnHandleCreated(EventArgs e)
    {
        base.OnHandleCreated(e);

        // Hotkey rahasia admin: Ctrl + Alt + Shift + B
        RegisterHotKey(Handle, 1, 0x0002 | 0x0001 | 0x0004, 0x42);
    }

    protected override void WndProc(ref Message m)
    {
        if (m.Msg == WmHotkey && m.WParam.ToInt32() == 1)
        {
            OpenAdminMode();

            return;
        }

        base.WndProc(ref m);
    }

    private void OpenAdminMode()
    {
        if (!_services.Config.HardeningEnabled || _services.Config.TestMode)
        {
            return;
        }

        _keyboardBlocker.SetEnabled(false);

        // Dialog kunci admin juga harus tampil di depan jendela kiosk TopMost.
        var wasTopMost = TopMost;
        TopMost = false;

        DialogResult unlockResult;

        try
        {
            using var unlock = new AdminUnlockForm(_services);

            unlockResult = unlock.ShowDialog(this);
        }
        finally
        {
            TopMost = wasTopMost;
            Activate();
        }

        if (unlockResult != DialogResult.OK)
        {
            _keyboardBlocker.SetEnabled(true);

            return;
        }

        // Menangguhkan kebijakan kiosk lewat Scheduled Task elevated yang dibuat
        // installer (runas + powershell diblokir DisallowRun saat hardening aktif).
        if (!KioskHardening.Suspend())
        {
            MessageBox.Show(
                this,
                "Hardening tidak dapat ditangguhkan (izin admin tidak diberikan).\n" +
                "Mode admin tetap dibuka, namun penguncian Task Manager/CMD masih aktif.",
                "PELOG",
                MessageBoxButtons.OK,
                MessageBoxIcon.Warning);
        }

        // Sembunyikan jendela kiosk selama mode admin agar desktop, cmd,
        // Settings, taskbar, dsb. benar-benar bisa dipakai.
        Hide();

        using (var admin = new AdminModeForm())
        {
            admin.ShowDialog();

            if (admin.ExitApplication)
            {
                // Tinggalkan mesin dalam keadaan normal: lepaskan kebijakan kiosk
                // agar cmd/Pengaturan/dll. tetap bisa dipakai setelah keluar.
                KioskHardening.Suspend();

                _allowExit = true;
                Application.Exit();

                return;
            }
        }

        KioskHardening.Apply();
        Show();
        Activate();
        _keyboardBlocker.SetEnabled(true);
    }

    /// <summary>
    /// Matikan laptop dari layar kunci: konfirmasi dulu, lalu shutdown Windows.
    /// (Di layar kunci tidak ada sesi aktif yang perlu ditutup.)
    /// </summary>
    private void ConfirmShutdown()
    {
        var confirm = MessageBox.Show(
            "Matikan laptop sekarang?",
            "PELOG",
            MessageBoxButtons.YesNo,
            MessageBoxIcon.Question,
            MessageBoxDefaultButton.Button2);

        if (confirm != DialogResult.Yes)
        {
            return;
        }

        LocalLog.Write(_services.DataDirectory, "tombol matikan (layar kunci) — Windows dimatikan");
        _idleTimer.Stop();

        try
        {
            System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo("shutdown.exe", "/s /t 0 /f")
            {
                CreateNoWindow = true,
                UseShellExecute = false,
            });
        }
        catch (Exception)
        {
            // Diabaikan; laptop tetap bisa dimatikan lewat menu Windows.
        }
    }

    /// <summary>
    /// Auto-shutdown saat idle di layar kunci (ambang dari pengaturan server;
    /// 0 = nonaktif). Tanpa input mouse/keyboard melebihi ambang → Windows dimatikan.
    /// </summary>
    private void CheckIdleShutdown()
    {
        var minutes = _services.Sync.LoadCachedConfig().IdleShutdownMinutes;

        if (minutes <= 0 || IdleDetector.IdleTime() < TimeSpan.FromMinutes(minutes))
        {
            return;
        }

        LocalLog.Write(_services.DataDirectory, $"layar kunci idle > {minutes} menit — Windows dimatikan");
        _idleTimer.Stop();

        try
        {
            System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo("shutdown.exe", "/s /t 0 /f")
            {
                CreateNoWindow = true,
                UseShellExecute = false,
            });
        }
        catch (Exception)
        {
            // Diabaikan.
        }
    }

    protected override void OnFormClosing(FormClosingEventArgs e)
    {
        if (!_allowExit && e.CloseReason == CloseReason.UserClosing)
        {
            e.Cancel = true;

            return;
        }

        _clockTimer.Stop();
        _serverTimer.Stop();
        _syncTimer.Stop();
        _idleTimer.Stop();
        _keyboardBlocker.Dispose();

        base.OnFormClosing(e);
    }
}
