namespace BalilogKiosk.App.Forms;

/// <summary>
/// Tombol kecil "Matikan / Selesai" yang tampil SELAMA sesi berjalan (mode dinamis).
///
/// Kenapa perlu: saat siswa menekan Shutdown/Restart lewat menu Windows, Windows
/// menampilkan layar penuh "Closing apps and shutting down" DI ATAS semua jendela
/// aplikasi, sehingga form refleksi kita tidak terlihat dan siswa berakhir di layar
/// Cancel/Sign in. Tombol ini memberi jalur yang andal: refleksi dulu, lalu komputer
/// dimatikan oleh aplikasi.
/// </summary>
internal sealed class PowerButtonForm : Form
{
    private static readonly Color Navy = Color.FromArgb(27, 58, 92);
    private static readonly Color Gold = Color.FromArgb(201, 162, 39);

    public event EventHandler? PowerRequested;

    public PowerButtonForm()
    {
        FormBorderStyle = FormBorderStyle.None;
        ShowInTaskbar = false;
        TopMost = true;
        StartPosition = FormStartPosition.Manual;
        Size = new Size(208, 46);
        BackColor = Navy;
        Font = new Font("Segoe UI", 9F);

        var button = new Button
        {
            Dock = DockStyle.Fill,
            Text = "🔌  Matikan / Selesai",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.White,
            BackColor = Navy,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
            TabStop = false,
        };

        button.FlatAppearance.BorderSize = 1;
        button.FlatAppearance.BorderColor = Gold;
        button.Click += (_, _) => PowerRequested?.Invoke(this, EventArgs.Empty);

        Controls.Add(button);

        var area = Screen.PrimaryScreen?.WorkingArea ?? new Rectangle(0, 0, 1024, 768);

        Location = new Point(area.Right - Width - 14, area.Bottom - Height - 14);
    }

    // Jangan mencuri fokus siswa saat muncul.
    protected override bool ShowWithoutActivation => true;
}
