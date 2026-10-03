using System.Diagnostics;
using PelogKiosk.App.Services;

namespace PelogKiosk.App.Forms;

/// <summary>
/// Banner MODE ADMIN: hardening ditangguhkan sementara untuk maintenance.
/// Menyediakan tombol pintasan yang dijalankan langsung (CreateProcess),
/// sehingga tetap berfungsi meski sebagian kebijakan shell masih aktif.
/// </summary>
public sealed class AdminModeForm : Form
{
    public bool ExitApplication { get; private set; }

    public AdminModeForm()
    {
        Text = "PELOG — Mode Admin";
        FormBorderStyle = FormBorderStyle.FixedToolWindow;
        StartPosition = FormStartPosition.Manual;
        TopMost = true;
        ShowInTaskbar = true;
        BackColor = Color.FromArgb(15, 34, 55);
        ForeColor = Color.White;
        ClientSize = new Size(430, 240);
        Font = new Font("Segoe UI", 9.5F);

        var area = Screen.PrimaryScreen?.WorkingArea ?? new Rectangle(0, 0, 1280, 720);
        Location = new Point(area.Right - Width - 20, area.Top + 20);

        var badge = new Label
        {
            Text = "🔓  MODE ADMIN AKTIF",
            Font = new Font("Segoe UI", 12F, FontStyle.Bold),
            ForeColor = Color.FromArgb(255, 214, 120),
            Location = new Point(18, 14),
            AutoSize = true,
        };

        var note = new Label
        {
            Text = "Hardening ditangguhkan. Bila masih ada yang terblokir, klik \"Muat Ulang Shell\".",
            Font = new Font("Segoe UI", 8.5F),
            ForeColor = Color.FromArgb(180, 200, 220),
            Location = new Point(20, 44),
            Size = new Size(396, 20),
        };

        var cmdButton = CreateToolButton("Buka CMD", 20, 72, () => Launch("cmd.exe"));
        var taskmgrButton = CreateToolButton("Task Manager", 152, 72, () => Launch("taskmgr.exe"));
        var settingsButton = CreateToolButton("Pengaturan", 284, 72, () => Launch("ms-settings:", viaShell: true));

        var regeditButton = CreateToolButton("Regedit", 20, 108, () => Launch("regedit.exe"));
        var powershellButton = CreateToolButton("PowerShell", 152, 108, () => Launch("powershell.exe"));
        var explorerButton = CreateToolButton("Explorer", 284, 108, () => Launch("explorer.exe"));

        var refreshButton = new Button
        {
            Text = "Muat Ulang Shell (segarkan kebijakan)",
            Location = new Point(20, 144),
            Size = new Size(390, 30),
            Font = new Font("Segoe UI", 8.5F),
            BackColor = Color.FromArgb(40, 62, 40),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        refreshButton.FlatAppearance.BorderColor = Color.FromArgb(90, 130, 90);
        refreshButton.Click += (_, _) => Task.Run(KioskHardening.RestartExplorer);

        var backButton = new Button
        {
            Text = "KEMBALI KE KIOSK",
            Location = new Point(20, 190),
            Size = new Size(210, 40),
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
            Location = new Point(240, 190),
            Size = new Size(170, 40),
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

        Controls.AddRange([
            badge, note,
            cmdButton, taskmgrButton, settingsButton,
            regeditButton, powershellButton, explorerButton,
            refreshButton,
            backButton, exitButton,
        ]);
    }

    private static Button CreateToolButton(string text, int x, int y, Action action)
    {
        var button = new Button
        {
            Text = text,
            Location = new Point(x, y),
            Size = new Size(126, 30),
            Font = new Font("Segoe UI", 8.5F),
            BackColor = Color.FromArgb(28, 52, 78),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        button.FlatAppearance.BorderColor = Color.FromArgb(70, 100, 130);
        button.Click += (_, _) => action();

        return button;
    }

    /// <summary>
    /// Jalankan langsung lewat CreateProcess (bukan shell) supaya tidak terkena
    /// kebijakan DisallowRun walau kebijakan sedang aktif.
    /// </summary>
    private static void Launch(string target, bool viaShell = false)
    {
        try
        {
            Process.Start(new ProcessStartInfo(target) { UseShellExecute = viaShell });
        }
        catch (Exception ex)
        {
            MessageBox.Show(
                "Gagal membuka " + target + ":\n\n" + ex.Message,
                "PELOG",
                MessageBoxButtons.OK,
                MessageBoxIcon.Warning);
        }
    }
}
