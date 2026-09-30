using System.Net.NetworkInformation;
using BalilogKiosk.Core;
using BalilogKiosk.Core.Models;
using BalilogKiosk.Core.Security;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Dialog pendaftaran perangkat (sekali per laptop).
/// Menukar kode enrollment menjadi device token yang disimpan dengan DPAPI.
/// </summary>
public sealed class EnrollForm : Form
{
    private readonly AppServices _services;

    private readonly TextBox _codeInput;

    private readonly TextBox _labelInput;

    private readonly Label _statusLabel;

    private readonly Button _enrollButton;

    public EnrollForm(AppServices services)
    {
        _services = services;

        Text = "BALI-LOG — Pendaftaran Perangkat";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        StartPosition = FormStartPosition.CenterScreen;
        MaximizeBox = false;
        MinimizeBox = false;
        BackColor = Color.FromArgb(15, 34, 55);
        ForeColor = Color.White;
        ClientSize = new Size(520, 462);
        Font = new Font("Segoe UI", 10F);

        var logo = new PictureBox
        {
            Image = LoadLogo(),
            SizeMode = PictureBoxSizeMode.Zoom,
            Size = new Size(72, 72),
            Location = new Point((ClientSize.Width - 72) / 2, 24),
        };

        var title = new Label
        {
            Text = "Pendaftaran Perangkat",
            Font = new Font("Segoe UI", 16F, FontStyle.Bold),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width, 32),
            Location = new Point(0, 104),
        };

        var subtitle = new Label
        {
            Text = "Masukkan kode enrollment dari Admin IT sekolah.\nKode hanya diperlukan sekali untuk laptop ini.",
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(180, 200, 220),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width - 40, 44),
            Location = new Point(20, 140),
        };

        var codeLabel = new Label
        {
            Text = "Kode Enrollment",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(200, 215, 230),
            Location = new Point(40, 200),
            AutoSize = true,
        };

        _codeInput = new TextBox
        {
            Location = new Point(40, 220),
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
            Location = new Point(40, 278),
            AutoSize = true,
        };

        _labelInput = new TextBox
        {
            Location = new Point(40, 302),
            Width = ClientSize.Width - 80,
            Height = 32,
            Font = new Font("Segoe UI", 12F),
            Text = Environment.MachineName,
            BorderStyle = BorderStyle.FixedSingle,
        };

        _statusLabel = new Label
        {
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Size = new Size(ClientSize.Width - 80, 24),
            Location = new Point(40, 344),
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(255, 210, 120),
            Text = "Laptop: " + Environment.MachineName,
        };

        _enrollButton = new Button
        {
            Text = "D A F T A R K A N",
            Location = new Point(40, 380),
            Size = new Size(ClientSize.Width - 80, 48),
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            BackColor = Color.FromArgb(201, 162, 39),
            ForeColor = Color.FromArgb(20, 26, 34),
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        _enrollButton.FlatAppearance.BorderSize = 0;
        _enrollButton.Click += async (_, _) => await EnrollAsync();

        Controls.AddRange([logo, title, subtitle, codeLabel, _codeInput, labelCaption, _labelInput, _statusLabel, _enrollButton]);

        AcceptButton = _enrollButton;
        ActiveControl = _codeInput;
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

    private async Task EnrollAsync()
    {
        var code = _codeInput.Text.Trim();

        if (code.Length < 4)
        {
            _statusLabel.Text = "Kode enrollment tidak valid.";
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
            _services.Config.Save(Path.Combine(Path.GetDirectoryName(_services.DataDirectory)!, "balilog.json"));

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
}
