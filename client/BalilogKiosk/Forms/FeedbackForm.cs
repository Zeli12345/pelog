using BalilogKiosk.Core.Data;

namespace BalilogKiosk.App.Forms;

/// <summary>
/// Form refleksi belajar siswa di akhir sesi (teks + tingkat pemahaman).
///
/// WAJIB diisi: teks minimal <see cref="MinFeedbackLength"/> huruf dan tingkat
/// pemahaman harus dipilih. Tombol X sengaja dimatikan (ControlBox = false) agar
/// siswa tidak bisa menutup form tanpa memilih SIMPAN atau BATAL.
/// </summary>
public sealed class FeedbackForm : Form
{
    private const int MinFeedbackLength = 10;

    private readonly TextBox _feedbackInput;

    private readonly Label _errorLabel;

    private readonly Dictionary<string, RadioButton> _levels = [];

    private readonly string _studentName;

    public string? Feedback { get; private set; }

    public string? Comprehension { get; private set; }

    public FeedbackForm(string studentName, bool shutdownMode = false)
    {
        _studentName = studentName;

        Text = "Refleksi Hasil Belajar";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        ControlBox = false;
        StartPosition = FormStartPosition.CenterScreen;
        MaximizeBox = false;
        MinimizeBox = false;
        BackColor = Color.FromArgb(247, 245, 241);
        ClientSize = new Size(620, 596);
        Font = new Font("Segoe UI", 10F);

        var title = new Label
        {
            Text = shutdownMode ? "Refleksi Sebelum Mematikan" : "Refleksi Hasil Belajar",
            Font = new Font("Segoe UI", 18F, FontStyle.Bold),
            ForeColor = Color.FromArgb(25, 28, 32),
            Location = new Point(32, 24),
            AutoSize = true,
        };

        var subtitle = new Label
        {
            Text = shutdownMode
                ? $"{_studentName}, laptop ini akan dimatikan. Isi refleksi belajar kamu dulu agar catatan sesi tersimpan."
                : $"{_studentName}, sebelum mengakhiri sesi silakan isi ringkasan belajar kamu.",
            Font = new Font("Segoe UI", 9.5F),
            ForeColor = Color.FromArgb(90, 96, 105),
            Location = new Point(34, 62),
            AutoSize = true,
            MaximumSize = new Size(556, 0),
        };

        var feedbackLabel = new Label
        {
            Text = $"Apa yang telah kamu pelajari / selesaikan hari ini? (minimal {MinFeedbackLength} huruf)",
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            ForeColor = Color.FromArgb(40, 44, 52),
            Location = new Point(32, 104),
            AutoSize = true,
        };

        _feedbackInput = new TextBox
        {
            Multiline = true,
            Location = new Point(32, 130),
            Size = new Size(556, 150),
            Font = new Font("Segoe UI", 11F),
            ScrollBars = ScrollBars.Vertical,
            BorderStyle = BorderStyle.FixedSingle,
        };

        var levelLabel = new Label
        {
            Text = "Tingkat pemahaman materi hari ini: (wajib dipilih)",
            Font = new Font("Segoe UI", 10F, FontStyle.Bold),
            ForeColor = Color.FromArgb(40, 44, 52),
            Location = new Point(32, 300),
            AutoSize = true,
        };

        var options = new (string Key, string Text)[]
        {
            ("sangat_paham", "Sangat Paham"),
            ("paham", "Paham"),
            ("cukup", "Cukup"),
            ("kurang", "Kurang Paham"),
        };

        var y = 332;

        foreach (var option in options)
        {
            var radio = new RadioButton
            {
                Text = option.Text,
                Location = new Point(40, y),
                AutoSize = true,
                Font = new Font("Segoe UI", 10.5F),
                ForeColor = Color.FromArgb(40, 44, 52),
            };

            // Sengaja TIDAK ada pilihan tercentang otomatis - siswa harus memilih.
            _levels[option.Key] = radio;
            Controls.Add(radio);

            y += 34;
        }

        _errorLabel = new Label
        {
            Text = string.Empty,
            Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
            ForeColor = Color.FromArgb(178, 58, 46),
            Location = new Point(32, 500),
            Size = new Size(556, 34),
        };

        var saveButton = new Button
        {
            Text = shutdownMode
                ? "S I M P A N   &   M A T I K A N"
                : "S I M P A N   &   K U N C I   L A P T O P",
            Location = new Point(232, 536),
            Size = new Size(356, 52),
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            BackColor = Color.FromArgb(27, 58, 92),
            ForeColor = Color.White,
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        saveButton.FlatAppearance.BorderSize = 0;
        saveButton.Click += (_, _) =>
        {
            var text = _feedbackInput.Text.Trim();

            if (text.Length < MinFeedbackLength)
            {
                _errorLabel.Text = $"Refleksi wajib diisi - tulis minimal {MinFeedbackLength} huruf.";
                _feedbackInput.Focus();

                return;
            }

            var level = _levels.FirstOrDefault(pair => pair.Value.Checked).Key;

            if (string.IsNullOrEmpty(level))
            {
                _errorLabel.Text = "Pilih dulu tingkat pemahaman materi hari ini.";
                _levels["sangat_paham"].Focus();

                return;
            }

            Feedback = text;
            Comprehension = level;

            DialogResult = DialogResult.OK;
            Close();
        };

        Controls.AddRange([title, subtitle, feedbackLabel, _feedbackInput, levelLabel, _errorLabel, saveButton]);

        // Tombol BATAL selalu ada: pada mode shutdown membatalkan pemadaman,
        // pada mode biasa kembali ke sesi (refleksi tidak tersimpan).
        var cancelButton = new Button
        {
            Text = "B A T A L",
            Location = new Point(32, 536),
            Size = new Size(180, 52),
            Font = new Font("Segoe UI", 11F, FontStyle.Bold),
            BackColor = Color.FromArgb(228, 231, 235),
            ForeColor = Color.FromArgb(40, 44, 52),
            FlatStyle = FlatStyle.Flat,
            Cursor = Cursors.Hand,
        };

        cancelButton.FlatAppearance.BorderSize = 0;
        cancelButton.Click += (_, _) =>
        {
            DialogResult = DialogResult.Cancel;
            Close();
        };

        Controls.Add(cancelButton);

        CancelButton = cancelButton;

        AcceptButton = saveButton;
        ActiveControl = _feedbackInput;
    }
}
