====================================================================
BALI-LOG KIOSK — CATATAN OPERASIONAL UNTUK ADMIN IT
SMK Negeri 1 Mas Ubud
====================================================================

1. CARA KELUAR / MAINTENANCE (tanpa uninstall)
   - Tekan Ctrl + Alt + Shift + B pada layar kiosk.
   - Masukkan password admin kiosk (diatur saat pertama kali diminta).
   - Setujui prompt UAC agar hardening ditangguhkan sementara.
   - Klik "KEMBALI KE KIOSK" bila selesai, atau "Keluar Aplikasi"
     untuk menghentikan kiosk sepenuhnya (maintenance berat).

2. CARA MENGAKHIRI SESI SISWA (tanpa widget di desktop)
   - Ikon BALI-LOG berada di system tray (dekat jam). Arahkan kursor
     untuk melihat durasi berjalan.
   - Klik DUA KALI ikon tersebut, atau klik kanan > "Selesai Penggunaan",
     lalu isi form refleksi belajar.
   - Alternatif cepat: tekan Ctrl + Alt + S.
   - Sesi juga tertutup otomatis saat siswa log off / mematikan laptop.

3. JIKA LAPTOP TERKUNCI DAN TIDAK BISA APA-APA
   - Log in dengan AKUN ADMIN WINDOWS (akun kiosk siswa tidak terpengaruh
     karena kebijakan hanya dipasang pada akun siswa).
   - Jalankan: unlock-admin.ps1 (ada di folder instalasi aplikasi) SETELAH
     login ke akun siswa tersebut. Bisa juga lewat:
       powershell -ExecutionPolicy Bypass -File unlock-admin.ps1
   - Untuk membersihkan autostart juga, tambahkan parameter -FullUninstall.

4. RESET PASSWORD ADMIN KIOSK
   - Buka file:  C:\ProgramData\BALI-LOG\balilog.json
   - Hapus baris "admin_password_hash", simpan, lalu buka kiosk kembali.
   - Password baru akan diminta saat menekan Ctrl+Alt+Shift+B berikutnya.

5. LOKASI DATA PENTING
   - Konfigurasi : C:\ProgramData\BALI-LOG\balilog.json
   - Database lokal (cache & antrean) : C:\ProgramData\BALI-LOG\data\local.db
   - Screenshot tertunda (offline)    : C:\ProgramData\BALI-LOG\data\screenshots
   - Token perangkat disimpan terenkripsi (DPAPI) di database lokal.

6. MENAMBAH / MENGHAPUS LAPTOP
   - Setiap laptop melakukan enrollment sekali dengan kode dari dashboard
     (Pengaturan > Enrollment Perangkat > Buat Kode Enrollment Baru).
   - Kode hanya tampil sekali. Bila hilang, buat kode baru — laptop yang
     sudah terdaftar tidak terpengaruh.
   - Uninstall: Settings > Apps > BALI-LOG Kiosk, atau:
       "C:\Program Files\BALI-LOG Kiosk\unins000.exe"

7. CATATAN PENTING
   - Instalasi sebaiknya dilakukan saat login dengan AKUN SISWA pada laptop
     tersebut, agar scheduled task watchdog terikat ke akun yang tepat.
     (Autostart utama sudah berjalan untuk semua akun via HKLM Run.)
   - Jangan menonaktifkan Windows Update secara paksa; sediakan jadwal
     maintenance berkala untuk pembaruan sistem.
   - Screenshot disimpan di server dengan retensi sesuai Pengaturan dashboard.

====================================================================
