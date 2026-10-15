using PelogKiosk.App.Forms;
using PelogKiosk.App.Services;

namespace PelogKiosk.App;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        using var mutex = new Mutex(true, @"Local\PELOG-Kiosk", out var isFirstInstance);

        if (!isFirstInstance)
        {
            // Instance lain sudah berjalan (mis. dipicu watchdog) — keluar diam-diam
            // tanpa dialog agar tidak mengganggu pengguna.
            return;
        }

        ApplicationConfiguration.Initialize();

        try
        {
            var services = AppServices.Create();

            // Muat token perangkat; kalau belum ada atau ditolak server (mis.
            // perangkat dicabut), tampilkan dialog enrollment.
            var hasDeviceToken = services.TryLoadDeviceToken();

            if (hasDeviceToken)
            {
                // Batas singkat: jaringan yang tidak merespons jangan menahan start kiosk.
                using var verifyTimeout = new CancellationTokenSource(TimeSpan.FromSeconds(5));

                var tokenStatus = services.VerifyDeviceTokenAsync(verifyTimeout.Token).GetAwaiter().GetResult();

                if (tokenStatus == DeviceTokenStatus.Revoked)
                {
                    // Perangkat dihapus dari dashboard: jangan tawarkan pendaftaran
                    // ulang. Jalankan wipe total bila diizinkan konfigurasi.
                    if (services.Config.SelfWipeOnRevoke)
                    {
                        SelfWipeService.Trigger("startup: perangkat dihapus dari dashboard");
                    }
                    else
                    {
                        LocalLog.Write(
                            services.DataDirectory,
                            "device_revoked saat startup — SelfWipeOnRevoke=false, wipe tidak dijalankan.");

                        // Token tetap tersimpan: kiosk berjalan dengan layar kunci,
                        // bukan menawarkan pendaftaran ulang perangkat yang dihapus.
                        hasDeviceToken = true;
                    }
                }
                else
                {
                    hasDeviceToken = tokenStatus == DeviceTokenStatus.Valid;
                }
            }

            if (!hasDeviceToken)
            {
                // Tahap pendaftaran BUKAN fase penegakan kiosk. Bersihkan sisa
                // kebijakan hardening (mis. dari installer) agar desktop TIDAK
                // PERNAH terkunci tanpa kiosk berjalan — termasuk saat laptop
                // dimatikan di tengah tahap ini dan di-boot ulang. Setelah
                // pendaftaran sukses, LockscreenForm menerapkan kebijakan lagi.
                KioskHardening.EnsurePoliciesRemoved();

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
            // Kiosk gagal berjalan (mis. data lokal rusak akibat mati listrik):
            // JANGAN meninggalkan desktop terkunci tanpa kiosk. Kebijakan dilepas
            // sebagai pengguna itu sendiri (HKCU), dengan fallback task elevated.
            KioskHardening.EnsurePoliciesRemoved();

            LocalLog.Write(
                Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
                    "PELOG",
                    "data"),
                "startup kiosk GAGAL — kebijakan dilepas, desktop bebas. Penyebab: " + ex.Message);

            MessageBox.Show(
                "Aplikasi gagal dijalankan:\n\n" + ex.Message + "\n\n" +
                "Penguncian kiosk dilepas agar laptop tetap normal.\n" +
                "IT dapat memperbaiki lalu menjalankan ulang PELOG.",
                "PELOG — Kesalahan",
                MessageBoxButtons.OK,
                MessageBoxIcon.Error);
        }
    }
}
