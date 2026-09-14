using BalilogKiosk.App.Forms;

namespace BalilogKiosk.App;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        using var mutex = new Mutex(true, @"Local\BALI-LOG-Kiosk", out var isFirstInstance);

        if (!isFirstInstance)
        {
            MessageBox.Show(
                "BALI-LOG sudah berjalan pada komputer ini.",
                "BALI-LOG",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);

            return;
        }

        ApplicationConfiguration.Initialize();

        try
        {
            var services = AppServices.Create();

            // Muat token perangkat; kalau belum ada, tampilkan dialog enrollment.
            if (!services.TryLoadDeviceToken())
            {
                using var enroll = new EnrollForm(services);

                if (enroll.ShowDialog() != DialogResult.OK)
                {
                    return;
                }
            }

            Application.Run(new LockscreenForm(services));
        }
        catch (Exception ex)
        {
            MessageBox.Show(
                "Aplikasi gagal dijalankan:\n\n" + ex.Message,
                "BALI-LOG — Kesalahan",
                MessageBoxButtons.OK,
                MessageBoxIcon.Error);
        }
    }
}
