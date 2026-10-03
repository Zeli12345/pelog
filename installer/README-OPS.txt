====================================================================
PELOG KIOSK — CATATAN OPERASIONAL UNTUK ADMIN IT
SMK Negeri 1 Mas Ubud
====================================================================

1. CARA KELUAR / MAINTENANCE (tanpa uninstall)
   - Tekan Ctrl + Alt + Shift + B pada layar kiosk.
   - Masukkan password admin kiosk (diatur saat pertama kali diminta).
   - Mode Admin aktif: jendela kiosk DISEMBUNYIKAN, desktop bisa dipakai;
     penguncian kiosk ditangguhkan sementara lewat Scheduled Task
     "PelogHardeningSuspend" (tanpa prompt UAC), lalu shell (explorer)
     dimuat ulang otomatis agar kebijakan lama tidak tersisa.
   - Form admin menyediakan pintasan: Buka CMD, Task Manager, Pengaturan,
     Regedit, PowerShell, Explorer, dan MUAT ULANG SHELL (bila masih ada
     aplikasi yang terblokir oleh kebijakan lama).
   - Klik "KEMBALI KE KIOSK" bila selesai (kebijakan dipasang ulang), atau
     "Keluar Aplikasi" untuk menghentikan kiosk sepenuhnya — pada opsi ini
     penguncian juga DILEPAS agar cmd/Pengaturan/dll. normal kembali.
   - Bila muncul peringatan "Hardening tidak dapat ditangguhkan":
     task admin tidak ada (instalasi lama) — jalankan installer ulang.
     Sementara itu, tombol PowerShell/Pengaturan/Explorer tetap berfungsi
     (dijalankan langsung tanpa shell), dan dari PowerShell admin dapat
     menghapus kebijakan secara manual:
       Remove-ItemProperty 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer' -Name NoRun,NoControlPanel,DisallowRun -EA SilentlyContinue
       Remove-ItemProperty 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\System' -Name DisableTaskMgr,DisableRegistryTools -EA SilentlyContinue
       Remove-ItemProperty 'HKCU:\Software\Policies\Microsoft\Windows\System' -Name DisableCMD -EA SilentlyContinue
     lalu jalankan "Muat Ulang Shell" (atau log off/log in).

2. CARA MENGAKHIRI SESI SISWA (tanpa widget/tray di desktop)
   - LOGIN SISWA: masukkan NISN, lalu TANGGAL LAHIR (format DD-MM-YYYY,
     mis. 31-12-2008), lalu pilih mapel + tujuan penggunaan.
   - Tekan Ctrl + Alt + S (fokus di mana saja) untuk mengakhiri sesi;
     muncul konfirmasi "Akhiri sesi sekarang?" - pilih Ya.
     (Tidak ada lagi form refleksi.)
   - SELAMA SESI BERJALAN, seluruh aplikasi berfungsi normal: Run (Win+R),
     CMD, PowerShell, Task Manager, Regedit, Pengaturan, dll. (penguncian
     kiosk hanya aktif saat layar kunci / tidak ada sesi).
   - Begitu sesi berakhir (atau laptop kembali ke layar kunci), penguncian
     kiosk dipasang ulang otomatis - pada SESI BERIKUTNYA bebas lagi.
   - SHUTDOWN / RESTART: bebas - siswa boleh mematikan laptop dari menu
     Windows (Start > Power) atau cara lain. Aplikasi hanya mencatat sesi
     sebagai "shutdown" lalu Windows mematikan laptop seperti biasa
     (tidak ada layar penahan / Cancel lagi).
   - Log off / sign out: sesi ditutup otomatis (alasan "shutdown").
   - Shutdown paksa (shutdown /f, tahan tombol power) atau mati listrik:
     catatan sesi tetap aman (sesi menggantung ditutup server sebagai
     "recovery").
   - Diagnosa: C:\ProgramData\PELOG\data\shutdown.log.
   - Sesi yang tertinggal karena mati listrik / aplikasi dihentikan paksa
     akan dipulihkan: dilanjutkan bila baru, atau ditutup sebagai
     "recovery" oleh server (pemantau sesi menggantung).
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
   - Buka file:  C:\ProgramData\PELOG\pelog.json
   - Hapus baris "admin_password_hash", simpan, lalu buka kiosk kembali.
   - Password baru akan diminta saat menekan Ctrl+Alt+Shift+B berikutnya.

7. LOKASI DATA PENTING
   - Konfigurasi : C:\ProgramData\PELOG\pelog.json
   - Database lokal (cache & antrean) : C:\ProgramData\PELOG\data\local.db
   - Screenshot tertunda (offline)    : C:\ProgramData\PELOG\data\screenshots
   - Token perangkat disimpan terenkripsi (DPAPI) di database lokal.

8. MENAMBAH / MENGHAPUS LAPTOP
   - Setiap laptop melakukan enrollment sekali dengan kode dari dashboard
     (Pengaturan > Enrollment Perangkat > Buat Kode Enrollment Baru).
   - Kode hanya tampil sekali. Bila hilang, buat kode baru — laptop yang
     sudah terdaftar tidak terpengaruh.
   - Uninstall: Settings > Apps > PELOG Kiosk, atau:
       "C:\Program Files\PELOG Kiosk\unins000.exe"

9. CATATAN PENTING
   - Instalasi sebaiknya dilakukan saat login dengan AKUN SISWA VIA akun
     tersebut (agar task watchdog & task admin terikat ke akun yang tepat).
     (Autostart utama sudah berjalan untuk semua akun via HKLM Run.)
   - Jangan menonaktifkan Windows Update secara paksa; sediakan jadwal
     maintenance berkala untuk pembaruan sistem.
   - Screenshot disimpan di server dengan retensi sesuai Pengaturan dashboard.

10. MENYAMBUNGKAN WI-FI DARI LAYAR KUNCI
   - Di layar kunci kiosk terdapat tombol "Wi-Fi" pada baris status bawah.
   - Tekan tombol tersebut untuk melihat daftar jaringan, memilih jaringan
     sekolah, mengisi password bila diminta, lalu tekan "Hubungkan".
   - Jaringan yang sudah pernah tersimpan dapat disambungkan lewat daftar
     "profil tersimpan" - tidak perlu mengetik password lagi.
   - Tidak memerlukan hak administrator: profil baru dibuat sebagai profil
     pengguna Windows (user=current).
   - Bila hanya jaringan tertentu yang boleh dipakai siswa, isi daftar SSID
     pada C:\ProgramData\PELOG\pelog.json, contoh:
       "allowed_wifi_ssids": ["SMKN1-UBUD", "LAB-RPL"]
     Kosongkan ([]) agar semua jaringan tampil. Perubahan berlaku setelah
     aplikasi kiosk dijalankan ulang.
   - Bila muncul "Tidak ada jaringan Wi-Fi terdeteksi": periksa adaptor /
     tombol Wi-Fi laptop, lalu tekan "Muat ulang".

11. MENGHAPUS PERANGKAT DARI DASHBOARD (WIPE OTOMATIS)
   - Buka dashboard > menu Perangkat > pilih perangkat yang akan dihapus >
     tekan tombol Hapus, lalu konfirmasi. Perangkat langsung hilang dari
     daftar dashboard dan tidak bisa lagi login/check-in.
   - Saat laptop tersebut terhubung kembali ke server (sinkronisasi berkala
     atau boot berikutnya), kiosk menerima status bahwa perangkat sudah
     dihapus dan menjalankan Scheduled Task "PelogSelfWipe" (hak admin,
     TANPA prompt UAC).
   - Tugas self-wipe menjalankan hardening.ps1 -Wipe dengan tahapan:
     1) menghentikan PelogKiosk.exe,
     2) menghapus semua Scheduled Task & autostart PELOG,
     3) menjalankan uninstaller secara senyap (VERYSILENT, tanpa UAC),
     4) menghapus folder data C:\ProgramData\PELOG.
   - PENTING - TIDAK DAPAT DIBATALKAN (IRREVERSIBLE): aplikasi, konfigurasi,
     database lokal, token perangkat, dan screenshot tertunda ikut terhapus.
     Laptop harus DIINSTAL ULANG (PELOG_Setup.exe) dan DI-ENROLL ULANG
     dengan kode enrollment baru sebelum bisa dipakai lagi.
   - Log wipe ada di laptop:  C:\Users\Public\pelog-wipe.log
     Baris terakhir "wipe selesai" menandakan proses tuntas. Bila laptop
     sempat mati di tengah proses, jalankan installer ulang atau ulangi wipe
     secara manual (dari akun admin):
       powershell -ExecutionPolicy Bypass -File "C:\Program Files\PELOG Kiosk\hardening.ps1" -Wipe
   - Wipe hanya menghapus aplikasi & data PELOG; akun Windows siswa dan
     Windows itu sendiri TIDAK dihapus.

   Mengatur nama/lokasi perangkat saat membangun installer:
   - Saat enrolment, isi kolom "Nama perangkat (label)" pada dialog
     Pendaftaran Perangkat (default: nama komputer Windows).
   - Dari dashboard kapan saja: Perangkat > pilih perangkat > panel
     "Pengaturan Perangkat" > ubah "Nama perangkat" dan "Lokasi" > Simpan.
   - Saat build installer (Admin IT), pakai parameter baru:
       .\scripts\build-installer.ps1 -DeviceLabel "LAB-BL-09" -Location "Lab RPL 1"
     Nilai ini ditulis ke pelog.client.json di folder installer dan
     disalin ke C:\ProgramData\PELOG\pelog.json saat instalasi (hanya
     bila file tujuan belum ada). Contoh isi:
       "device_label": "LAB-BL-09",
       "device_location": "Lab RPL 1"
   - Bisa juga mengedit langsung C:\ProgramData\PELOG\pelog.json lalu
     menjalankan ulang aplikasi kiosk.

====================================================================
