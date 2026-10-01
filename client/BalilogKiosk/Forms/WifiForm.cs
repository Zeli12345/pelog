using BalilogKiosk.App.Services;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Dialog Wi-Fi untuk layar kunci kiosk: memindai jaringan, menyambung ke
/// profil tersimpan, atau membuat profil baru (password diminta) tanpa keluar
/// dari aplikasi kiosk.
/// </summary>
internal sealed class WifiForm : Form
{
    private static readonly Color NavyDark = Color.FromArgb(15, 34, 55);
    private static readonly Color Navy = Color.FromArgb(27, 58, 92);
    private static readonly Color Gold = Color.FromArgb(201, 162, 39);
    private static readonly Color Paper = Color.FromArgb(247, 245, 241);
    private static readonly Color Ink = Color.FromArgb(25, 28, 32);
    private static readonly Color InkSoft = Color.FromArgb(90, 96, 105);
    private static readonly Color Brick = Color.FromArgb(178, 58, 46);
    private static readonly Color Moss = Color.FromArgb(47, 125, 92);

    private readonly List<string> _allowed;
    private readonly ListView _networkList;
    private readonly ComboBox _profileCombo;
    private readonly TextBox _passwordInput;
    private readonly Label _passwordLabel;
    private readonly Button _connectButton;
    private readonly Button _profileButton;
    private readonly Button _refreshButton;
    private readonly Button _closeButton;
    private readonly Label _statusLabel;
    private readonly Label _messageLabel;

    private bool _busy;

    public WifiForm(IEnumerable<string>? allowedSsids = null)
    {
        _allowed = (allowedSsids ?? [])
            .Where(ssid => !string.IsNullOrWhiteSpace(ssid))
            .Select(ssid => ssid.Trim())
            .ToList();

        Text = "Koneksi Wi-Fi — BALI-LOG";
        ClientSize = new Size(640, 584);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        ShowInTaskbar = false;
        TopMost = true;
        BackColor = Paper;
        Font = new Font("Segoe UI", 10F);

        // ---------- Header ----------
        var header = new Panel { Dock = DockStyle.Top, Height = 74, BackColor = NavyDark };

        var title = new Label
        {
            Text = "Koneksi Wi-Fi",
            Font = new Font("Segoe UI", 15F, FontStyle.Bold),
            ForeColor = Color.White,
            Location = new Point(24, 12),
            AutoSize = true,
        };

        var subtitle = new Label
        {
            Text = "Sambungkan laptop ke jaringan sekolah tanpa keluar dari kiosk.",
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(180, 200, 220),
            Location = new Point(26, 44),
            AutoSize = true,
        };

        header.Controls.AddRange([title, subtitle]);

        // ---------- Status ----------
        _statusLabel = new Label
        {
            Text = "Status: memeriksa…",
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            ForeColor = Navy,
            Location = new Point(24, 88),
            AutoSize = true,
        };

        var networkLabel = new Label
        {
            Text = "Jaringan yang tersedia",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = InkSoft,
            Location = new Point(24, 118),
            AutoSize = true,
        };

        // ---------- Daftar jaringan ----------
        _networkList = new ListView
        {
            Location = new Point(24, 140),
            Size = new Size(592, 170),
            View = View.Details,
            FullRowSelect = true,
            MultiSelect = false,
            HideSelection = false,
            Font = new Font("Segoe UI", 9.5F),
        };

        _networkList.Columns.Add("Nama jaringan", 320);
        _networkList.Columns.Add("Keamanan", 160);
        _networkList.Columns.Add("Sinyal", 90);
        _networkList.SelectedIndexChanged += (_, _) => UpdateConnectState();
        _networkList.DoubleClick += (_, _) => OnConnectClicked(null, EventArgs.Empty);

        // ---------- Password ----------
        _passwordLabel = new Label
        {
            Text = "Password jaringan",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Ink,
            Location = new Point(24, 320),
            AutoSize = true,
        };

        _passwordInput = new TextBox
        {
            Location = new Point(24, 342),
            Size = new Size(384, 28),
            UseSystemPasswordChar = true,
            Font = new Font("Segoe UI", 10F),
            Enabled = false,
        };

        _passwordInput.KeyDown += (_, e) =>
        {
            if (e.KeyCode == Keys.Enter)
            {
                OnConnectClicked(null, EventArgs.Empty);
                e.SuppressKeyPress = true;
            }
        };

        _connectButton = new Button
        {
            Text = "Hubungkan",
            Location = new Point(420, 340),
            Size = new Size(196, 32),
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            BackColor = Navy,
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        _connectButton.FlatAppearance.BorderSize = 0;
        _connectButton.Click += (_, _) => OnConnectClicked(null, EventArgs.Empty);

        // ---------- Profil tersimpan ----------
        var profileLabel = new Label
        {
            Text = "Atau sambung ke profil yang sudah tersimpan",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = InkSoft,
            Location = new Point(24, 386),
            AutoSize = true,
        };

        _profileCombo = new ComboBox
        {
            Location = new Point(24, 408),
            Size = new Size(384, 28),
            DropDownStyle = ComboBoxStyle.DropDownList,
            Font = new Font("Segoe UI", 9.5F),
        };

        _profileButton = new Button
        {
            Text = "Sambungkan profil",
            Location = new Point(420, 406),
            Size = new Size(196, 32),
            Font = new Font("Segoe UI", 9.5F),
            BackColor = Color.White,
            ForeColor = Navy,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        _profileButton.FlatAppearance.BorderSize = 1;
        _profileButton.FlatAppearance.BorderColor = Gold;
        _profileButton.Click += (_, _) => OnProfileConnectClicked();

        // ---------- Aksi ----------
        _refreshButton = new Button
        {
            Text = "Muat ulang",
            Location = new Point(24, 456),
            Size = new Size(150, 34),
            Font = new Font("Segoe UI", 9.5F),
            BackColor = Color.White,
            ForeColor = Ink,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        _refreshButton.FlatAppearance.BorderSize = 1;
        _refreshButton.FlatAppearance.BorderColor = Color.FromArgb(203, 196, 192);
        _refreshButton.Click += async (_, _) => await RefreshAsync();

        _closeButton = new Button
        {
            Text = "Tutup",
            Location = new Point(466, 456),
            Size = new Size(150, 34),
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            BackColor = Gold,
            ForeColor = NavyDark,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
            DialogResult = DialogResult.OK,
        };

        _closeButton.FlatAppearance.BorderSize = 0;

        _messageLabel = new Label
        {
            Text = string.Empty,
            Font = new Font("Segoe UI", 9F),
            ForeColor = InkSoft,
            Location = new Point(24, 500),
            Size = new Size(592, 60),
        };

        Controls.AddRange([
            header,
            _statusLabel,
            networkLabel,
            _networkList,
            _passwordLabel,
            _passwordInput,
            _connectButton,
            profileLabel,
            _profileCombo,
            _profileButton,
            _refreshButton,
            _closeButton,
            _messageLabel,
        ]);

        AcceptButton = _connectButton;
        CancelButton = _closeButton;

        Shown += async (_, _) => await RefreshAsync();
    }

    private async Task RefreshAsync()
    {
        if (_busy)
        {
            return;
        }

        SetBusy(true, "Memindai jaringan Wi-Fi…");

        try
        {
            var (networks, profiles, current) = await Task.Run(() =>
            {
                var nets = WifiService.ScanNetworks();
                var profs = WifiService.SavedProfiles();
                var currentSsid = WifiService.CurrentSsid();

                return (nets, profs, currentSsid);
            });

            if (_allowed.Count > 0)
            {
                networks = networks.Where(n => _allowed.Contains(n.Ssid, StringComparer.OrdinalIgnoreCase)).ToList();
                profiles = profiles.Where(p => _allowed.Contains(p, StringComparer.OrdinalIgnoreCase)).ToList();
            }

            FillNetworks(networks);
            FillProfiles(profiles);

            _statusLabel.Text = current is null
                ? "Status: belum tersambung ke jaringan Wi-Fi"
                : "Status: tersambung ke " + current;

            UpdateConnectState();

            var hint = networks.Count == 0
                ? "Tidak ada jaringan Wi-Fi terdeteksi. Periksa adaptor Wi-Fi, lalu tekan Muat ulang."
                : $"{networks.Count} jaringan ditemukan.";

            if (_allowed.Count > 0)
            {
                hint += "  (Hanya jaringan yang diizinkan sekolah yang ditampilkan.)";
            }

            SetBusy(false, hint);
        }
        catch (Exception ex)
        {
            SetBusy(false, "Gagal memindai jaringan: " + ex.Message);
        }
    }

    private void FillNetworks(List<WifiNetwork> networks)
    {
        _networkList.BeginUpdate();
        _networkList.Items.Clear();

        foreach (var network in networks)
        {
            var item = new ListViewItem(network.Ssid) { Tag = network };
            item.SubItems.Add(network.SecurityText);
            item.SubItems.Add(network.SignalText);
            _networkList.Items.Add(item);
        }

        _networkList.EndUpdate();

        if (_networkList.Items.Count > 0)
        {
            _networkList.Items[0].Selected = true;
        }
    }

    private void FillProfiles(List<string> profiles)
    {
        var selected = _profileCombo.SelectedItem as string;

        _profileCombo.BeginUpdate();
        _profileCombo.Items.Clear();

        foreach (var profile in profiles)
        {
            _profileCombo.Items.Add(profile);
        }

        if (selected is not null && _profileCombo.Items.Contains(selected))
        {
            _profileCombo.SelectedItem = selected;
        }
        else if (_profileCombo.Items.Count > 0)
        {
            _profileCombo.SelectedIndex = 0;
        }

        _profileCombo.EndUpdate();
    }

    private WifiNetwork? SelectedNetwork()
        => _networkList.SelectedItems.Count > 0 ? _networkList.SelectedItems[0].Tag as WifiNetwork : null;

    private bool HasSavedProfile(string ssid)
        => _profileCombo.Items.Cast<string>().Any(profile => profile.Equals(ssid, StringComparison.OrdinalIgnoreCase));

    private void UpdateConnectState()
    {
        var network = SelectedNetwork();

        if (network is null)
        {
            _passwordLabel.Text = "Password jaringan (pilih jaringan dahulu)";
            _passwordInput.Enabled = false;
            return;
        }

        if (HasSavedProfile(network.Ssid))
        {
            _passwordLabel.Text = $"\"{network.Ssid}\" sudah tersimpan — tinggal tekan Hubungkan";
            _passwordInput.Enabled = false;
            return;
        }

        if (network.IsOpen)
        {
            _passwordLabel.Text = $"\"{network.Ssid}\" jaringan terbuka — tinggal tekan Hubungkan";
            _passwordInput.Enabled = false;
            return;
        }

        _passwordLabel.Text = $"Password jaringan \"{network.Ssid}\"";
        _passwordInput.Enabled = true;
    }

    private async void OnConnectClicked(object? sender, EventArgs e)
    {
        if (_busy)
        {
            return;
        }

        var network = SelectedNetwork();

        if (network is null)
        {
            ShowMessage("Pilih jaringan pada daftar terlebih dahulu.", Brick);
            return;
        }

        var saved = HasSavedProfile(network.Ssid);
        var password = network.IsOpen ? null : _passwordInput.Text;

        if (!saved && !network.IsOpen && string.IsNullOrEmpty(password))
        {
            ShowMessage("Isi password jaringan terlebih dahulu.", Brick);
            _passwordInput.Focus();
            return;
        }

        SetBusy(true, $"Menyambungkan ke {network.Ssid}…");

        try
        {
            var (ok, message) = await Task.Run(() =>
            {
                var success = saved
                    ? WifiService.ConnectProfile(network.Ssid, out var text)
                    : WifiService.ConnectNew(network.Ssid, password, network.Security, out text);

                return (success, text);
            });

            SetBusy(false, null);

            if (ok)
            {
                _passwordInput.Clear();
                ShowMessage(message, Moss);
                await RefreshAsync();
            }
            else
            {
                ShowMessage(message, Brick);
                UpdateConnectState();
            }
        }
        catch (Exception ex)
        {
            SetBusy(false, null);
            ShowMessage("Gagal menyambung: " + ex.Message, Brick);
        }
    }

    private async void OnProfileConnectClicked()
    {
        if (_busy)
        {
            return;
        }

        if (_profileCombo.SelectedItem is not string profile)
        {
            ShowMessage("Belum ada profil tersimpan yang dipilih.", Brick);
            return;
        }

        SetBusy(true, $"Menyambungkan ke {profile}…");

        try
        {
            var (ok, message) = await Task.Run(() =>
            {
                var success = WifiService.ConnectProfile(profile, out var text);

                return (success, text);
            });

            SetBusy(false, null);

            if (ok)
            {
                ShowMessage(message, Moss);
                await RefreshAsync();
            }
            else
            {
                ShowMessage(message, Brick);
            }
        }
        catch (Exception ex)
        {
            SetBusy(false, null);
            ShowMessage("Gagal menyambung: " + ex.Message, Brick);
        }
    }

    private void SetBusy(bool busy, string? message)
    {
        _busy = busy;
        _refreshButton.Enabled = !busy;
        _connectButton.Enabled = !busy;
        _profileButton.Enabled = !busy;
        Cursor = busy ? Cursors.WaitCursor : Cursors.Default;

        if (message is not null)
        {
            ShowMessage(message, InkSoft);
        }
    }

    private void ShowMessage(string message, Color color)
    {
        _messageLabel.Text = message;
        _messageLabel.ForeColor = color;
    }
}
