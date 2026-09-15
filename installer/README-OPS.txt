====================================================================
BALI-LOG KIOSK — CATATAN OPERASIONAL UNTUK ADMIN IT
SMK Negeri 1 Mas Ubud
====================================================================

1. CARA KELUAR / MAINTENANCE (tanpa uninstall)
   - Tekan Ctrl + Alt + Shift + B pada layar kiosk.
   - Masukkan password admin kiosk (diatur saat pertama kali diminta).
   - Mode Admin aktif: penguncian kiosk ditangguhkan sementara (lewat
     Scheduled Task "BALILogHardeningSuspend" — tanpa prompt UAC).
   - Klik "KEMBALI KE KIOSK" bila selesai, atau "Keluar Aplikasi"
     untuk menghentikan kiosk sepenuhnya (maintenance berat).
   - Bila muncul peringatan "Hardening tidak dapat ditangguhkan":
     task admin tidak ada (instalasi lama) — jalankan installer ulang,
     atau login dengan AKUN ADMIN WINDOWS untuk perbaikan.

2. CARA MENGAKHIRI SESI SISWA (tanpa widget di desktop)
   - Ikon BALI-LOG berada di system tray (dekat jam). Arahkan kursor
     untuk melihat durasi berjalan.
   - Klik DUA KALI ikon tersebut, atau klik kanan > "Selesai Penggunaan",
     lalu isi form refleksi belajar.
   - Alternatif cepat: tekan Ctrl + Alt + S.
   - Sesi juga tertutup otomatis saat siswa log off / mematikan laptop.
   - Admin IT juga dapat menutup paksa sesi dari dashboard:
     menu Sesi Penggunaan > tombol "Tutup" pada baris sesi aktif.

3. MEMBERI NAMA PERANGKAT (mis. LAB-BL-09)
   - Saat pendaftaran (pertama kali aplikasi dibuka): isi kolom
     "Nama perangkat (label)" pada dialog Pendaftaran Perangkat.
   - Kapan saja setelahnya: buka dashboard > Perangkat > pilih perangkat >
     panel "Pengaturan Perangkat" > ubah "Nama perangkat" & "Lokasi" >
     Simpan. Nama ini yang tampil di dashboard, laporan, dan screenshot.

4. MODE PERAWATAN PERANGKAT
   - Buka dashboard > Perangkat > pilih perangkat > panel
     "Pengaturan Perangkat" > Status: "Perawatan" > Simpan Pengaturan.
   - Dampak: laptop TIDAK dapat memulai penggunaan baru (login siswa/guru
     ditolak dengan pesan "Laptop ini sedang dalam perawatan Admin IT").
     Sesi yang sedang berjalan tetap berlanjut sampai diakhiri; admin
     dapat menutupnya dari menu Sesi Penggunaan.
   - Kembalikan ke "Tersedia (normal)" setelah perawatan selesai.

5. JIKA LAPTOP TERKUNCI DAN TIDAK BISA APA-APA
   - Log in dengan AKUN ADMIN WINDOWS (akun kiosk siswa tidak terpengaruh
     karena kebijakan hanya dipasang pada akun siswa).
   - Jalankan: unlock-admin.ps1 (ada di folder instalasi aplikasi) SETELAH
     login ke akun siswa tersebut. Bisa juga lewat:
       powershell -ExecutionPolicy Bypass -File unlock-admin.ps1
   - Untuk membersihkan autostart juga, tambahkan parameter -FullUninstall.
   - Catatan: saat penguncian aktif, cmd/PowerShell/.bat diblokir di akun
     siswa. Bila perlu pemeliharaan dari akun siswa, gunakan file .vbs
     (contoh: scripts\vm-reset-kiosk.vbs) atau akun admin Windows.

6. RESET PASSWORD ADMIN KIOSK
   - Buka file:  C:\ProgramData\BALI-LOG\balilog.json
   - Hapus baris "admin_password_hash", simpan, lalu buka kiosk kembali.
   - Password baru akan diminta saat menekan Ctrl+Alt+Shift+B berikutnya.

7. LOKASI DATA PENTING
   - Konfigurasi : C:\ProgramData\BALI-LOG\balilog.json
   - Database lokal (cache & antrean) : C:\ProgramData\BALI-LOG\data\local.db
   - Screenshot tertunda (offline)    : C:\ProgramData\BALI-LOG\data\screenshots
   - Token perangkat disimpan terenkripsi (DPAPI) di database lokal.

8. MENAMBAH / MENGHAPUS LAPTOP
   - Setiap laptop melakukan enrollment sekali dengan kode dari dashboard
     (Pengaturan > Enrollment Perangkat > Buat Kode Enrollment Baru).
   - Kode hanya tampil sekali. Bila hilang, buat kode baru — laptop yang
     sudah terdaftar tidak terpengaruh.
   - Uninstall: Settings > Apps > BALI-LOG Kiosk, atau:
       "C:\Program Files\BALI-LOG Kiosk\unins000.exe"

9. CATATAN PENTING
   - Instalasi sebaiknya dilakukan saat login dengan AKUN SISWA VIA akun
     tersebut (agar task watchdog & task admin terikat ke akun yang tepat).
     (Autostart utama sudah berjalan untuk semua akun via HKLM Run.)
   - Jangan menonaktifkan Windows Update secara paksa; sediakan jadwal
     maintenance berkala untuk pembaruan sistem.
   - Screenshot disimpan di server dengan retensi sesuai Pengaturan dashboard.

====================================================================
