using System.Net.NetworkInformation;
using PelogKiosk.Core;
using PelogKiosk.Core.Models;
using PelogKiosk.Core.Security;

namespace PelogKiosk.App.Forms;

/// <summary>
/// Dialog pendaftaran perangkat (sekali per laptop).
/// Menukar kode enrollment menjadi device token yang disimpan dengan DPAPI.
/// Lokasi dipilih dari daftar ruangan; admin/guru juga boleh menambah ruangan baru.
/// </summary>
public sealed class EnrollForm : Form
{
    private readonly AppServices _services;

    private readonly TextBox _codeInput;

    private readonly TextBox _labelInput;

    private readonly ComboBox _locationCombo;

    private readonly TextBox _newLocationInput;

    private readonly Label _locationHint;

    private readonly Label _statusLabel;

    private readonly Button _enrollButton;

    private bool _locationsLoaded;

    public EnrollForm(AppServices services)
    {
        _services = services;

        Text = "PELOG — Pendaftaran Perangkat";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        StartPosition = FormStartPosition.CenterScreen;
        MaximizeBox = false;
        MinimizeBox = false;
        BackColor = Color.FromArgb(15, 34, 55);
        ForeColor = Color.White;
        ClientSize = new Size(520, 596);
        Font = new Font("Segoe UI", 10F);

        var logo = new PictureBox
        {
            Image = LoadLogo(),
            SizeMode = PictureBoxSizeMode.Zoom,
            Size = new Size(72, 72),
            Location = new Point((ClientSize.Width - 72) / 2, 20),
        };

        var title = new Label
        {
            Text = "Pendaftaran Perangkat",
            Font = new Font("Segoe UI", 16F, FontStyle.Bold),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width, 32),
            Location = new Point(0, 96),
        };

        var subtitle = new Label
        {
            Text = "Masukkan kode enrollment dari Admin IT sekolah.\nKode hanya diperlukan sekali untuk laptop ini.",
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(180, 200, 220),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width - 40, 42),
            Location = new Point(20, 130),
        };

        var codeLabel = new Label
        {
            Text = "Kode Enrollment",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(200, 215, 230),
            Location = new Point(40, 186),
            AutoSize = true,
        };

        _codeInput = new TextBox
        {
            Location = new Point(40, 206),
            Width = ClientSize.Width - 80,
            Height = 40,
            Font = new Font("Consolas", 16F, FontStyle.Bold),
            TextAlign = HorizontalAlignment.Center,
            CharacterCasing = CharacterCasing.Upper,
            Text = _services.Config.EnrollmentCode,
            BorderStyle = BorderStyle.FixedSingle,
        };

        var labelCaption = new Label
        {
            Text = "Nama perangkat (label)",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(200, 215, 230),
            Location = new Point(40, 258),
            AutoSize = true,
        };

        _labelInput = new TextBox
        {
            Location = new Point(40, 280),
            Width = ClientSize.Width - 80,
            Height = 32,
            Font = new Font("Segoe UI", 12F),
            Text = string.IsNullOrWhiteSpace(_services.Config.DeviceLabel)
                ? Environment.MachineName
                : _services.Config.DeviceLabel.Trim(),
            BorderStyle = BorderStyle.FixedSingle,
        };

        var locationCaption = new Label
        {
            Text = "Ruangan / lokasi perangkat",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(200, 215, 230),
            Location = new Point(40, 324),
            AutoSize = true,
        };

        _locationCombo = new ComboBox
        {
            Location = new Point(40, 346),
            Width = ClientSize.Width - 180,
            Height = 32,
            Font = new Font("Segoe UI", 11F),
            DropDownStyle = ComboBoxStyle.DropDownList,
        };

        var addLocationButton = new Button
        {
            Text = "+ Baru",
            Location = new Point(ClientSize.Width - 132, 344),
            Size = new Size(92, 32),
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            BackColor = Color.FromArgb(35, 60, 88),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
        };
        addLocationButton.FlatAppearance.BorderColor = Color.FromArgb(80, 110, 140);
        addLocationButton.Click += (_, _) => ShowNewLocationInput();

        _newLocationInput = new TextBox
        {
            Location = new Point(40, 386),
            Width = ClientSize.Width - 80,
            Height = 30,
            Font = new Font("Segoe UI", 11F),
            MaxLength = 60,
            PlaceholderText = "Nama ruangan baru, mis. Lab RPL 2",
            BorderStyle = BorderStyle.FixedSingle,
            Visible = false,
        };

        _locationHint = new Label
        {
            Text = "Memuat daftar ruangan…",
            Font = new Font("Segoe UI", 8F),
            ForeColor = Color.FromArgb(160, 185, 210),
            Location = new Point(40, 418),
            AutoSize = false,
            Size = new Size(ClientSize.Width - 80, 18),
        };

        _statusLabel = new Label
        {
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width - 80, 24),
            Location = new Point(40, 448),
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(255, 210, 120),
            Text = "Laptop: " + Environment.MachineName,
        };

        _enrollButton = new Button
        {
            Text = "D A F T A R K A N",
            Location = new Point(40, 484),
            Size = new Size(ClientSize.Width - 80, 48),
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            BackColor = Color.FromArgb(201, 162, 39),
            ForeColor = Color.FromArgb(20, 26, 34),
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        _enrollButton.FlatAppearance.BorderSize = 0;
        _enrollButton.Click += async (_, _) => await EnrollAsync();

        Controls.AddRange([
            logo, title, subtitle, codeLabel, _codeInput, labelCaption, _labelInput,
            locationCaption, _locationCombo, addLocationButton, _newLocationInput,
            _locationHint, _statusLabel, _enrollButton,
        ]);

        AcceptButton = _enrollButton;
        ActiveControl = _codeInput;

        Shown += async (_, _) => await LoadLocationsAsync();
    }

    private static Image? LoadLogo()
    {
        var logoPath = Path.Combine(AppContext.BaseDirectory, "Assets", "logo_sekolah.png");

        if (File.Exists(logoPath))
        {
            using var stream = File.OpenRead(logoPath);

            return Image.FromStream(stream);
        }

        return null;
    }

    private async Task LoadLocationsAsync()
    {
        if (_locationsLoaded)
        {
            return;
        }

        _locationsLoaded = true;
        _locationCombo.Items.Clear();
        _locationCombo.Items.Add(new LocationChoice(0, "Memuat…"));
        _locationCombo.SelectedIndex = 0;
        _locationCombo.Enabled = false;

        using var timeout = new CancellationTokenSource(TimeSpan.FromSeconds(6));
        var result = await _services.Api.GetLocationsAsync(timeout.Token);

        _locationCombo.Items.Clear();
        _locationCombo.Enabled = true;

        if (result.Ok && result.Data is not null)
        {
            foreach (var location in result.Data.Locations.OrderBy(item => item.Name))
            {
                _locationCombo.Items.Add(new LocationChoice(location.Id, location.Name));
            }
        }

        _locationCombo.Items.Add(new LocationChoice(0, "+ Tambah ruangan baru…"));

        var preferred = _services.Config.DeviceLocation;
        var selected = _locationCombo.Items
            .OfType<LocationChoice>()
            .FirstOrDefault(item => item.Id > 0 && string.Equals(item.Name, preferred, StringComparison.OrdinalIgnoreCase));

        _locationCombo.SelectedItem = selected ?? _locationCombo.Items.OfType<LocationChoice>().FirstOrDefault();

        if (!result.Ok)
        {
            _locationHint.Text = "Daftar ruangan tidak dapat dimuat. Pilih “+ Tambah ruangan baru…” untuk mengetik nama.";
        }
        else if (result.Data?.Locations.Count == 0)
        {
            _locationHint.Text = "Belum ada ruangan. Tambahkan ruangan pertama.";
            ShowNewLocationInput();
        }
        else
        {
            _locationHint.Text = "Pilih ruangan yang sudah ada agar lokasi perangkat konsisten.";
        }
    }

    private void ShowNewLocationInput()
    {
        _locationCombo.SelectedItem = _locationCombo.Items
            .OfType<LocationChoice>()
            .LastOrDefault(item => item.Id == 0);

        _newLocationInput.Visible = true;
        _newLocationInput.Focus();
        _locationHint.Text = "Ketik nama ruangan baru. Nama yang mirip dengan ruangan lama akan memakai ruangan tersebut.";
    }

    private async Task EnrollAsync()
    {
        var code = _codeInput.Text.Trim();

        if (code.Length < 4)
        {
            _statusLabel.Text = "Kode enrollment tidak valid.";
            _statusLabel.ForeColor = Color.FromArgb(240, 150, 140);

            return;
        }

        var selected = _locationCombo.SelectedItem as LocationChoice;
        var newLocation = _newLocationInput.Visible ? _newLocationInput.Text.Trim() : string.Empty;

        if (selected is null || (selected.Id == 0 && newLocation.Length == 0))
        {
            _statusLabel.Text = "Pilih ruangan atau isi nama ruangan baru.";
            _statusLabel.ForeColor = Color.FromArgb(240, 150, 140);

            return;
        }

        _enrollButton.Enabled = false;
        _statusLabel.ForeColor = Color.FromArgb(180, 200, 220);
        _statusLabel.Text = "Menghubungi server…";

        try
        {
            var request = new EnrollRequest
            {
                EnrollmentCode = code,
                DeviceUuid = _services.GetOrCreateDeviceUuid(),
                Hostname = Environment.MachineName,
                Label = _labelInput.Text.Trim(),
                LocationId = selected.Id > 0 ? selected.Id : null,
                NewLocation = selected.Id == 0 ? newLocation : null,
                MacList = GetMacAddresses(),
                DeviceType = "laptop",
                AgentVersion = AppInfo.Version,
                WindowsVersion = Environment.OSVersion.VersionString,
                StorageTotalGb = GetStorageTotalGb(),
                StorageUsedGb = GetStorageUsedGb(),
            };

            var result = await _services.Api.EnrollAsync(request);

            if (!result.Ok || result.Data is null)
            {
                _statusLabel.ForeColor = Color.FromArgb(240, 150, 140);
                _statusLabel.Text = result.ErrorMessage ?? "Pendaftaran gagal.";

                return;
            }

            _services.SaveDeviceToken(result.Data.DeviceToken);
            _services.Config.EnrollmentCode = code;
            _services.Config.DeviceLocation = result.Data.Device?.Location?.Name
                ?? (selected.Id > 0 ? selected.Name : newLocation);
            _services.Config.DeviceLabel = result.Data.Device?.Label ?? _labelInput.Text.Trim();
            _services.Config.Save(Path.Combine(Path.GetDirectoryName(_services.DataDirectory)!, "pelog.json"));

            _statusLabel.ForeColor = Color.FromArgb(150, 220, 170);
            var deviceName = result.Data.Device?.Label ?? result.Data.Device?.Hostname ?? "perangkat ini";
            _statusLabel.Text = $"Berhasil terdaftar sebagai {deviceName}.";

            // Segarkan cache master data setelah enroll.
            await _services.Sync.RefreshBootstrapAsync();

            await Task.Delay(700);

            DialogResult = DialogResult.OK;
            Close();
        }
        finally
        {
            _enrollButton.Enabled = true;
        }
    }

    private static List<string> GetMacAddresses()
    {
        try
        {
            return NetworkInterface.GetAllNetworkInterfaces()
                .Where(nic => nic.NetworkInterfaceType is NetworkInterfaceType.Ethernet or NetworkInterfaceType.Wireless80211)
                .Select(nic => nic.GetPhysicalAddress().ToString())
                .Where(mac => !string.IsNullOrEmpty(mac))
                .ToList();
        }
        catch (Exception)
        {
            return [];
        }
    }

    private static int GetStorageTotalGb() => GetDriveInfo()?.TotalSize is long total ? (int)(total / 1024 / 1024 / 1024) : 0;

    private static int GetStorageUsedGb()
    {
        var drive = GetDriveInfo();

        return drive is null ? 0 : (int)((drive.TotalSize - drive.AvailableFreeSpace) / 1024 / 1024 / 1024);
    }

    private static DriveInfo? GetDriveInfo()
    {
        try
        {
            return new DriveInfo(Path.GetPathRoot(Environment.SystemDirectory)!);
        }
        catch (Exception)
        {
            return null;
        }
    }

    private sealed record LocationChoice(long Id, string Name)
    {
        public override string ToString() => Name;
    }
}
