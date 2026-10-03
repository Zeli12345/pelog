using PelogKiosk.App.Services;

namespace PelogKiosk.App.Forms;

/// <summary>
/// Dialog password admin untuk masuk MODE ADMIN (maintenance).
/// Jika password belum dikonfigurasi, admin dapat mengaturnya di sini.
/// </summary>
public sealed class AdminUnlockForm : Form
{
    private readonly AppServices _services;

    private readonly TextBox _passwordInput;

    private readonly TextBox? _confirmInput;

    private readonly Label _message;

    private readonly bool _setupMode;

    public AdminUnlockForm(AppServices services)
    {
        _services = services;
        _setupMode = string.IsNullOrEmpty(_services.Config.AdminPasswordHash);

        Text = _setupMode ? "PELOG — Atur Password Admin" : "PELOG — Mode Admin";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        StartPosition = FormStartPosition.CenterScreen;
        MaximizeBox = false;
        MinimizeBox = false;
        TopMost = true;
        BackColor = Color.FromArgb(15, 34, 55);
        ForeColor = Color.White;
        ClientSize = new Size(460, _setupMode ? 320 : 250);
        Font = new Font("Segoe UI", 10F);

        var title = new Label
        {
            Text = _setupMode ? "Atur password admin kiosk" : "Masukkan password admin",
            Font = new Font("Segoe UI", 13F, FontStyle.Bold),
            Location = new Point(28, 24),
            AutoSize = true,
        };

        var hint = new Label
        {
            Text = _setupMode
                ? "Password ini dipakai untuk membuka MODE ADMIN\n(maintenance). Simpan di tempat aman."
                : "Akses maintenance: hardening akan ditangguhkan sementara.",
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(170, 190, 210),
            Location = new Point(30, 58),
            Size = new Size(400, 40),
        };

        _passwordInput = new TextBox
        {
            Location = new Point(30, 110),
            Width = 396,
            Font = new Font("Consolas", 14F),
            UseSystemPasswordChar = true,
            BorderStyle = BorderStyle.FixedSingle,
        };

        Controls.AddRange([title, hint, _passwordInput]);

        if (_setupMode)
        {
            var confirmLabel = new Label
            {
                Text = "Ulangi password",
                Font = new Font("Segoe UI", 9F),
                ForeColor = Color.FromArgb(170, 190, 210),
                Location = new Point(30, 142),
                AutoSize = true,
            };

            _confirmInput = new TextBox
            {
                Location = new Point(30, 164),
                Width = 396,
                Font = new Font("Consolas", 14F),
                UseSystemPasswordChar = true,
                BorderStyle = BorderStyle.FixedSingle,
            };

            Controls.AddRange([confirmLabel, _confirmInput]);
        }

        _message = new Label
        {
            Location = new Point(30, _setupMode ? 200 : 142),
            Size = new Size(400, 24),
            Font = new Font("Segoe UI", 9F),
            ForeColor = Color.FromArgb(255, 190, 110),
        };

        var submit = new Button
        {
            Text = _setupMode ? "SIMPAN PASSWORD" : "BUKA MODE ADMIN",
            Location = new Point(30, _setupMode ? 232 : 172),
            Size = new Size(240, 44),
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            BackColor = Color.FromArgb(201, 162, 39),
            ForeColor = Color.FromArgb(25, 28, 32),
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        submit.FlatAppearance.BorderSize = 0;
        submit.Click += (_, _) => Submit();

        var cancel = new Button
        {
            Text = "Batal",
            Location = new Point(286, _setupMode ? 232 : 172),
            Size = new Size(140, 44),
            Font = new Font("Segoe UI", 9.5F),
            BackColor = Color.FromArgb(27, 58, 92),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        cancel.FlatAppearance.BorderSize = 0;
        cancel.Click += (_, _) =>
        {
            DialogResult = DialogResult.Cancel;
            Close();
        };

        Controls.AddRange([_message, submit, cancel]);

        AcceptButton = submit;
        ActiveControl = _passwordInput;
    }

    private void Submit()
    {
        var password = _passwordInput.Text;

        if (password.Length < 6)
        {
            _message.Text = "Password minimal 6 karakter.";

            return;
        }

        if (_setupMode)
        {
            if (password != _confirmInput?.Text)
            {
                _message.Text = "Ulangi password tidak sama.";

                return;
            }

            var made = AdminPasswordHasher.Make(password);
            _services.Config.AdminPasswordHash =
                $"{made.Algo}${made.Iterations}${made.Salt}${made.Hash}";

            _services.Config.Save(Path.Combine(
                Path.GetDirectoryName(_services.DataDirectory)!, "pelog.json"));

            DialogResult = DialogResult.OK;
            Close();

            return;
        }

        var parts = _services.Config.AdminPasswordHash?.Split('$');

        if (parts is null || parts.Length != 4)
        {
            _message.Text = "Format password tersimpan tidak valid.";

            return;
        }

        var valid = parts[0] == AdminPasswordHasher.Algo
            && int.TryParse(parts[1], out var iterations)
            && AdminPasswordHasher.Verify(password, parts[2], iterations, parts[3]);

        if (!valid)
        {
            _message.Text = "Password salah.";
            _passwordInput.Clear();
            _passwordInput.Focus();

            return;
        }

        DialogResult = DialogResult.OK;
        Close();
    }
}
