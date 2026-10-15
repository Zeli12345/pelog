using System.Text;

namespace PelogRevert;

/// <summary>
/// UI alat perbaikan: jalankan rincian rencana perbaikan dengan log yang bisa
/// dibaca Admin IT. Alat ini berjalan SEBAGAI ADMIN (manifest) dan meminta
/// konfirmasi sebelum penghapusan total.
/// </summary>
public sealed class MainForm : Form
{
    private readonly TextBox _log;
    private readonly CheckBox _fullUninstall;
    private readonly Button _start;

    public MainForm()
    {
        Text = "PELOG — Alat Perbaikan Laptop";
        Font = new Font("Segoe UI", 10F);
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedSingle;
        MaximizeBox = false;
        ClientSize = new Size(700, 600);
        BackColor = Color.FromArgb(15, 34, 55);
        ForeColor = Color.White;

        var title = new Label
        {
            Text = "Perbaiki laptop dari penguncian kiosk",
            Font = new Font("Segoe UI", 14F, FontStyle.Bold),
            AutoSize = true,
            Location = new Point(24, 16),
        };

        var intro = new Label
        {
            AutoSize = false,
            Size = new Size(652, 92),
            Location = new Point(24, 54),
            ForeColor = Color.FromArgb(180, 200, 220),
            Text = "Jalankan di laptop yang mengalami: CMD/PowerShell/Control Panel terkunci " +
                   "sehingga kiosk PELOG tidak melepasnya.\n\n" +
                   "Langkah: 1) menghentikan kiosk, 2) melepaskan seluruh kebijakan registry, " +
                   "3) menghapus scheduled task & autostart, 4) memuat ulang Explorer. " +
                   "Opsi total juga menjalankan uninstaller + menghapus sisa data.",
        };

        _fullUninstall = new CheckBox
        {
            Text = "Perbaiki sekaligus HAPUS instalasi + data PELOG (total)",
            Checked = true,
            AutoSize = true,
            Location = new Point(24, 156),
            ForeColor = Color.White,
        };

        _start = new Button
        {
            Text = "M U L A I   P E R B A I K I",
            BackColor = Color.FromArgb(201, 162, 39),
            ForeColor = Color.FromArgb(20, 26, 34),
            FlatStyle = FlatStyle.Flat,
            Size = new Size(652, 46),
            Location = new Point(24, 184),
        };
        _start.FlatAppearance.BorderSize = 0;

        _log = new TextBox
        {
            Multiline = true,
            ReadOnly = true,
            ScrollBars = ScrollBars.Vertical,
            BackColor = Color.FromArgb(8, 18, 28),
            ForeColor = Color.FromArgb(190, 215, 235),
            Font = new Font("Consolas", 9F),
            Size = new Size(652, 300),
            Location = new Point(24, 244),
            WordWrap = false,
        };

        var footer = new Label
        {
            AutoSize = true,
            Location = new Point(24, 552),
            ForeColor = Color.FromArgb(140, 160, 180),
            Text = "Setelah selesai: laptop kembali normal. Untuk memakai PELOG lagi, install dari dashboard sekolah.",
        };

        _start.Click += async (_, _) => await RunRevertAsync();

        Controls.AddRange([title, intro, _fullUninstall, _start, _log, footer]);
    }

    private void AppendLog(string line)
    {
        _log.AppendText(line + Environment.NewLine);
    }

    private async Task RunRevertAsync()
    {
        _start.Enabled = false;
        _fullUninstall.Enabled = false;
        _log.Clear();

        var fullUninstall = _fullUninstall.Checked;

        try
        {
            await Task.Run(() =>
            {
                var runner = new RevertRunner(line =>
                {
                    try
                    {
                        BeginInvoke(() => AppendLog(line));
                    }
                    catch (Exception)
                    {
                        // form mungkin sedang ditutup
                    }
                });

                runner.LogLine("=== PELOG Revert ===");
                runner.StopKiosk();
                runner.RemovePoliciesEverywhere();
                runner.DeleteScheduledTasks();
                runner.RemoveAutostart();

                if (fullUninstall)
                {
                    runner.RemoveInstallationData();
                }

                runner.RestartExplorer();
                runner.LogLine("=== SELESAI — laptop kembali normal ===");
            });

            MessageBox.Show(
                "Perbaikan selesai. Laptop kembali normal.\n\n" +
                "Log juga tersimpan di " + RevertPlan.PublicLogPath,
                "PELOG Revert",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show(
                "Perbaikan gagal:\n\n" + ex.Message,
                "PELOG Revert",
                MessageBoxButtons.OK,
                MessageBoxIcon.Error);
        }
        finally
        {
            _start.Enabled = true;
            _fullUninstall.Enabled = true;
        }
    }
}
