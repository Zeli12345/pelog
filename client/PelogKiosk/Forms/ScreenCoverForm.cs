namespace PelogKiosk.App.Forms;

/// <summary>
/// Panel penutup monitor non-utama saat kiosk berada di layar kunci:
/// borderless, selalu di atas, dan TIDAK pernah mengambil fokus keyboard
/// (WS_EX_NOACTIVATE) sehingga tidak ada celah pada layar kedua.
/// </summary>
public sealed class ScreenCoverForm : Form
{
    private const int WsExNoActivate = 0x08000000;

    private const int WsExToolWindow = 0x00000080;

    public ScreenCoverForm(Rectangle bounds)
    {
        FormBorderStyle = FormBorderStyle.None;
        StartPosition = FormStartPosition.Manual;
        Bounds = bounds;
        BackColor = Color.FromArgb(12, 28, 46);
        TopMost = true;
        ShowInTaskbar = false;
        MinimizeBox = false;
        MaximizeBox = false;
        ControlBox = false;
        Text = "PELOG";

        var brand = new Label
        {
            Text = "PELOG",
            Font = new Font("Segoe UI", 22F, FontStyle.Bold),
            ForeColor = Color.FromArgb(255, 214, 120),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Fill,
        };

        var hint = new Label
        {
            Text = "Gunakan monitor utama",
            Font = new Font("Segoe UI", 11F),
            ForeColor = Color.FromArgb(120, 145, 170),
            AutoSize = false,
            TextAlign = ContentAlignment.MiddleCenter,
            Dock = DockStyle.Bottom,
            Height = 64,
        };

        Controls.Add(brand);
        Controls.Add(hint);
    }

    protected override bool ShowWithoutActivation => true;

    protected override CreateParams CreateParams
    {
        get
        {
            var parameters = base.CreateParams;
            parameters.ExStyle |= WsExNoActivate | WsExToolWindow;

            return parameters;
        }
    }
}
