namespace BalilogKiosk.App.Forms;

/// <summary>
/// Banner MODE ADMIN: hardening ditangguhkan sementara untuk maintenance.
/// Admin dapat kembali ke kiosk atau keluar dari aplikasi.
/// </summary>
public sealed class AdminModeForm : Form
{
    public bool ExitApplication { get; private set; }

    public AdminModeForm()
    {
        Text = "BALI-LOG — Mode Admin";
        FormBorderStyle = FormBorderStyle.FixedToolWindow;
        StartPosition = FormStartPosition.Manual;
        TopMost = true;
        ShowInTaskbar = true;
        BackColor = Color.FromArgb(15, 34, 55);
        ForeColor = Color.White;
        ClientSize = new Size(430, 150);
        Font = new Font("Segoe UI", 9.5F);

        var area = Screen.PrimaryScreen?.WorkingArea ?? new Rectangle(0, 0, 1280, 720);
        Location = new Point(area.Right - Width - 20, area.Top + 20);

        var badge = new Label
        {
            Text = "🔓  MODE ADMIN AKTIF",
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Color.FromArgb(255, 214, 120),
            Location = new Point(18, 16),
            AutoSize = true,
        };

        var note = new Label
        {
            Text = "Hardening kiosk ditangguhkan sementara.\nIngat: kembali ke kiosk bila maintenance selesai.",
            Font = new Font("Segoe UI", 8.5F),
            ForeColor = Color.FromArgb(180, 200, 220),
            Location = new Point(20, 48),
            Size = new Size(392, 40),
        };

        var backButton = new Button
        {
            Text = "KEMBALI KE KIOSK",
            Location = new Point(20, 96),
            Size = new Size(210, 38),
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            BackColor = Color.FromArgb(201, 162, 39),
            ForeColor = Color.FromArgb(25, 28, 32),
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        backButton.FlatAppearance.BorderSize = 0;
        backButton.Click += (_, _) =>
        {
            DialogResult = DialogResult.OK;
            Close();
        };

        var exitButton = new Button
        {
            Text = "Keluar Aplikasi",
            Location = new Point(240, 96),
            Size = new Size(170, 38),
            Font = new Font("Segoe UI", 9F),
            BackColor = Color.FromArgb(178, 58, 46),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        exitButton.FlatAppearance.BorderSize = 0;
        exitButton.Click += (_, _) =>
        {
            ExitApplication = true;
            DialogResult = DialogResult.Cancel;
            Close();
        };

        Controls.AddRange([badge, note, backButton, exitButton]);
    }
}
