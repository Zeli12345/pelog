using System.Globalization;
using System.Text.RegularExpressions;
using BalilogKiosk.App.Services;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Security;
using BalilogKiosk.Core.Services;
using Timer = System.Windows.Forms.Timer;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Layar kunci kiosk BALI-LOG.
/// Alur: NISN/NIP -> (set/verifikasi PIN) -> mapel & tujuan -> sesi dimulai.
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
        PinSetup,
        PinVerify,
        Details,
    }

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

    private readonly Button _studentModeButton;
    private readonly Button _staffModeButton;

    private readonly Panel _card;

    private readonly Panel _identifyPanel;
    private readonly Label _identifyTitle;
    private readonly Label _identifyHint;
    private readonly TextBox _identifyInput;
    private readonly Label _identifyError;

    private readonly Panel _pinSetupPanel;
    private readonly Label _pinSetupName;
    private readonly TextBox _pinSetupInput;
    private readonly TextBox _pinSetupConfirmInput;
    private readonly Label _pinSetupError;

    private readonly Panel _pinVerifyPanel;
    private readonly Label _pinVerifyName;
    private readonly TextBox _pinVerifyInput;
    private readonly Label _pinVerifyError;

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

    private Mode _mode = Mode.Student;
    private CachedStudent? _student;
    private CachedStaff? _staff;
    private readonly KeyboardBlocker _keyboardBlocker = new();
    private bool _allowExit;
    private bool _busy;
    private bool _enrollmentRecoveryOpen;

    public LockscreenForm(AppServices services)
    {
        _services = services;

        // Pastikan kebijakan penguncian terpasang setiap aplikasi dijalankan
        // (mis. setelah "Keluar Aplikasi" lalu pengawas menghidupkan ulang kiosk).
        if (_services.Config.HardeningEnabled && !_services.Config.TestMode)
        {
            Task.Run(KioskHardening.Apply);
        }

        Text = "BALI-LOG — Kiosk";
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
            Text = "BALI-LOG",
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

        footer.Controls.AddRange([_serverLabel, deviceLabel]);
        footer.Resize += (_, _) => deviceLabel.Location = new Point(footer.Width - 456, 10);

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

        // ---------- Panel: Set PIN ----------
        _pinSetupPanel = new Panel { Location = new Point(36, 156), Size = new Size(568, 290), BackColor = Color.Transparent, Visible = false };

        _pinSetupName = new Label
        {
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Navy,
            Location = new Point(2, 0),
            AutoSize = true,
        };

        var pinSetupInstructions = new Label
        {
            Text = "Pertama kali login — buat PIN pribadimu.\nPilih 4 angka yang mudah kamu ingat, dan JANGAN bagikan ke teman.\nPIN ini untuk login di semua laptop sekolah.",
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = InkSoft,
            Location = new Point(4, 30),
            Size = new Size(560, 70),
            AutoSize = false,
        };

        var pin1Label = new Label { Text = "PIN baru", Font = new Font("Segoe UI", 9F, FontStyle.Bold), ForeColor = Ink, Location = new Point(2, 106), AutoSize = true };

        _pinSetupInput = new TextBox
        {
            Location = new Point(2, 128),
            Width = 200,
            Font = new Font("Consolas", 18F, FontStyle.Bold),
            UseSystemPasswordChar = true,
            MaxLength = 6,
            BorderStyle = BorderStyle.FixedSingle,
        };

        var pin2Label = new Label { Text = "Ulangi PIN", Font = new Font("Segoe UI", 9F, FontStyle.Bold), ForeColor = Ink, Location = new Point(230, 106), AutoSize = true };

        _pinSetupConfirmInput = new TextBox
        {
            Location = new Point(230, 128),
            Width = 200,
            Font = new Font("Consolas", 18F, FontStyle.Bold),
            UseSystemPasswordChar = true,
            MaxLength = 6,
            BorderStyle = BorderStyle.FixedSingle,
        };

        _pinSetupInput.KeyPress += OnlyDigits;
        _pinSetupConfirmInput.KeyPress += OnlyDigits;

        var pinSetupButton = new Button
        {
            Text = "S I M P A N   P I N",
            Location = new Point(2, 186),
            Size = new Size(428, 46),
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            BackColor = Moss,
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        pinSetupButton.FlatAppearance.BorderSize = 0;
        pinSetupButton.Click += async (_, _) => await SubmitPinSetupAsync();

        var pinSetupBack = CreateBackButton();
        pinSetupBack.Location = new Point(440, 186);
        pinSetupBack.Click += (_, _) => ResetFlow();

        _pinSetupError = new Label
        {
            Location = new Point(2, 240),
            Size = new Size(564, 44),
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = Brick,
            AutoSize = false,
        };

        _pinSetupPanel.Controls.AddRange([
            _pinSetupName, pinSetupInstructions, pin1Label, _pinSetupInput,
            pin2Label, _pinSetupConfirmInput, pinSetupButton, pinSetupBack, _pinSetupError,
        ]);

        // ---------- Panel: Verifikasi PIN ----------
        _pinVerifyPanel = new Panel { Location = new Point(36, 156), Size = new Size(568, 290), BackColor = Color.Transparent, Visible = false };

        _pinVerifyName = new Label
        {
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Navy,
            Location = new Point(2, 0),
            AutoSize = true,
        };

        var pinVerifyHint = new Label
        {
            Text = "Masukkan PIN yang pernah kamu buat.",
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = InkSoft,
            Location = new Point(4, 30),
            AutoSize = true,
        };

        _pinVerifyInput = new TextBox
        {
            Location = new Point(2, 62),
            Width = 240,
            Font = new Font("Consolas", 22F, FontStyle.Bold),
            UseSystemPasswordChar = true,
            MaxLength = 6,
            BorderStyle = BorderStyle.FixedSingle,
        };

        _pinVerifyInput.KeyPress += OnlyDigits;

        var pinVerifyButton = new Button
        {
            Text = "M A S U K",
            Location = new Point(2, 128),
            Size = new Size(428, 46),
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            BackColor = Navy,
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        pinVerifyButton.FlatAppearance.BorderSize = 0;
        pinVerifyButton.Click += async (_, _) => await SubmitPinVerifyAsync();

        var pinVerifyBack = CreateBackButton();
        pinVerifyBack.Location = new Point(440, 128);
        pinVerifyBack.Click += (_, _) => ResetFlow();

        _pinVerifyError = new Label
        {
            Location = new Point(2, 186),
            Size = new Size(564, 60),
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = Brick,
            AutoSize = false,
        };

        _pinVerifyPanel.Controls.AddRange([
            _pinVerifyName, pinVerifyHint, _pinVerifyInput, pinVerifyButton, pinVerifyBack, _pinVerifyError,
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
            _identifyPanel, _pinSetupPanel, _pinVerifyPanel, _detailsPanel,
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
            if (!await _services.VerifyDeviceTokenAsync())
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

        try
        {
            using var enroll = new EnrollForm(_services);

            enroll.ShowDialog(this);
        }
        finally
        {
            _enrollmentRecoveryOpen = false;
        }
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

        ShowStep(Step.Identify);
    }

    private void ShowStep(Step step)
    {
        _identifyPanel.Visible = step == Step.Identify;
        _pinSetupPanel.Visible = step == Step.PinSetup;
        _pinVerifyPanel.Visible = step == Step.PinVerify;
        _detailsPanel.Visible = step == Step.Details;

        switch (step)
        {
            case Step.Identify:
                _identifyInput.Focus();
                break;
            case Step.PinSetup:
                _pinSetupInput.Clear();
                _pinSetupConfirmInput.Clear();
                _pinSetupError.Text = string.Empty;
                _pinSetupInput.Focus();
                break;
            case Step.PinVerify:
                _pinVerifyInput.Clear();
                _pinVerifyError.Text = string.Empty;
                _pinVerifyInput.Focus();
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

                _student = student;

                if (student.HasPin)
                {
                    _pinVerifyName.Text = $"{student.Name} · {student.ClassName}";
                    ShowStep(Step.PinVerify);
                }
                else
                {
                    _pinSetupName.Text = $"{student.Name} · {student.ClassName}";
                    ShowStep(Step.PinSetup);
                }
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

    private async Task SubmitPinSetupAsync()
    {
        if (_busy || _student is null)
        {
            return;
        }

        _busy = true;

        try
        {
            var config = _services.Sync.LoadCachedConfig();
            var pin = _pinSetupInput.Text.Trim();
            var confirm = _pinSetupConfirmInput.Text.Trim();

            _pinSetupError.Text = string.Empty;

            if (pin.Length != config.PinLength)
            {
                _pinSetupError.Text = $"PIN harus {config.PinLength} angka.";

                return;
            }

            if (pin != confirm)
            {
                _pinSetupError.Text = "Ulangi PIN tidak sama. Coba lagi.";

                return;
            }

            if (PinPolicy.IsWeak(pin))
            {
                _pinSetupError.Text = "PIN terlalu mudah ditebak. Hindari angka berulang atau berurutan.";

                return;
            }

            // PIN selalu dibuat di server (hash kanonik), jadi wajib online.
            // SetPinAsync tidak pernah dipanggil saat offline; siswa dapat mencoba
            // lagi (dan server diperiksa ulang) setelah koneksi tersambung.
            if (!await _services.Api.HealthAsync())
            {
                _pinSetupError.Text = "PIN harus dibuat saat laptop tersambung ke server. Sambungkan koneksi, lalu tekan S I M P A N   P I N lagi.";

                return;
            }

            var result = await _services.Api.SetPinAsync(_student.Nisn, pin);

            if (!result.Ok)
            {
                if (result.ErrorCode == "pin_already_set")
                {
                    _pinSetupError.Text = "PIN sudah pernah dibuat di perangkat lain. Masuk dengan PIN yang dulu.";

                    _student = _services.Store.GetStudent(_student.Nisn) ?? _student;
                    _pinVerifyName.Text = $"{_student.Name} · {_student.ClassName}";
                    ShowStep(Step.PinVerify);

                    return;
                }

                _pinSetupError.Text = result.ErrorMessage ?? "Gagal menyimpan PIN.";

                return;
            }

            // PIN tersimpan di server; simpan hash lokal untuk verifikasi offline berikutnya.
            var pinData = result.Data?.Pin;
            var made = PinHasher.Make(pin);

            _services.Store.UpdateStudentPin(
                _student.Nisn,
                pinData?.Algo ?? made.Algo,
                pinData?.Salt ?? made.Salt,
                pinData?.Iterations ?? made.Iterations,
                pinData?.Hash ?? made.Hash);

            _student = _services.Store.GetStudent(_student.Nisn) ?? _student;

            PrepareDetails();
        }
        finally
        {
            _busy = false;
        }
    }

    private async Task SubmitPinVerifyAsync()
    {
        if (_busy || _student is null)
        {
            return;
        }

        _busy = true;

        try
        {
            var config = _services.Sync.LoadCachedConfig();
            var pin = _pinVerifyInput.Text.Trim();

            _pinVerifyError.Text = string.Empty;

            var (failed, lockedUntil) = _services.Store.GetPinState(_student.Nisn);

            if (lockedUntil is not null && lockedUntil > _services.Clock.Now)
            {
                _pinVerifyError.Text = $"PIN terkunci sementara. Coba lagi pukul {lockedUntil.Value.ToOffset(TimeSpan.FromHours(8)):HH:mm} WITA, atau lapor guru/IT.";

                return;
            }

            var pinData = _student.Pin;

            if (pinData?.Salt is null || pinData.Hash is null)
            {
                _pinVerifyError.Text = "Data PIN belum tersedia di laptop ini. Lapor guru/IT untuk reset.";

                return;
            }

            if (!PinHasher.Verify(pin, pinData.Salt, pinData.Iterations, pinData.Hash))
            {
                failed++;

                if (failed >= config.PinMaxAttempts)
                {
                    _services.Store.SavePinState(_student.Nisn, failed, _services.Clock.Now.AddMinutes(config.PinLockMinutes));
                    _pinVerifyError.Text = $"PIN salah {failed}×. Terkunci {config.PinLockMinutes} menit. Lapor guru/IT bila lupa PIN.";
                }
                else
                {
                    _services.Store.SavePinState(_student.Nisn, failed, null);
                    _pinVerifyError.Text = $"PIN salah ({failed}/{config.PinMaxAttempts}). Coba lagi.";
                }

                _pinVerifyInput.Clear();

                return;
            }

            _services.Store.SavePinState(_student.Nisn, 0, null);

            PrepareDetails();

            await Task.CompletedTask;
        }
        finally
        {
            _busy = false;
        }
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

                LaunchSession(record, _student.Name);
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

                LaunchSession(record, _staff.Name);
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

    private void LaunchSession(Core.Data.LocalSessionRecord record, string displayName)
    {
        Hide();

        // Sesi berjalan: desktop dipakai normal (Run, CMD, Pengaturan, dll. bebas).
        EnterSessionMode();

        // Runtime sesi berjalan tanpa jendela: hotkey Ctrl+Alt+S (cadangan Ctrl+Alt+E) untuk mengakhiri.
        var runtime = new ActiveSessionRuntime(_services, record, displayName);

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

        Task.Run(KioskHardening.Suspend);
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

        _identifyInput.Clear();
        _identifyError.Text = string.Empty;
        _pinSetupInput.Clear();
        _pinSetupConfirmInput.Clear();
        _pinSetupError.Text = string.Empty;
        _pinVerifyInput.Clear();
        _pinVerifyError.Text = string.Empty;
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

        // Sesi masih segar -> lanjutkan dengan widget yang sama.
        var record = recovery.Record;
        var displayName = "Pengguna";

        if (record.UserType == "student" && record.Nisn is not null)
        {
            var student = _services.Store.GetStudent(record.Nisn);
            displayName = student?.Name ?? record.Nisn;
        }
        else if (record.NipId is not null)
        {
            var staff = _services.Store.GetStaff(record.NipId);
            displayName = staff?.Name ?? record.NipId;
        }

        BeginInvoke(() => LaunchSession(record, displayName));
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

        using (var unlock = new AdminUnlockForm(_services))
        {
            if (unlock.ShowDialog(this) != DialogResult.OK)
            {
                _keyboardBlocker.SetEnabled(true);

                return;
            }
        }

        // Menangguhkan kebijakan kiosk lewat Scheduled Task elevated yang dibuat
        // installer (runas + powershell diblokir DisallowRun saat hardening aktif).
        if (!KioskHardening.Suspend())
        {
            MessageBox.Show(
                this,
                "Hardening tidak dapat ditangguhkan (izin admin tidak diberikan).\n" +
                "Mode admin tetap dibuka, namun penguncian Task Manager/CMD masih aktif.",
                "BALI-LOG",
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
        _keyboardBlocker.Dispose();

        base.OnFormClosing(e);
    }
}
