# VERIFICATION â€” PELOG

Laporan verifikasi menyeluruh. Diperbarui: 15 September 2026 (Fase 6 selesai di VM).
Semua perintah dijalankan dari root repo `pelog/` kecuali disebut lain.

---

## 1. Cara Menjalankan Seluruh Verifikasi

```powershell
# 0) Siapkan environment + database staging
.\scripts\env.ps1
.\scripts\dev-db.ps1

# 1) Test server (80 test)
cd server; php artisan test; cd ..

# 2) Test client + integrasi E2E (27 test)
#    E2E membutuhkan server staging hidup + kode enrollment
$code = (php server\artisan pelog:enrollment-code | Select-String "BLG-").Matches[0].Value
$env:PELOG_E2E_CODE = $code
dotnet test Pelog.sln

# 3) Build aplikasi + installer
.\scripts\build-client.ps1
.\scripts\build-installer.ps1 -BaseUrl "http://<IP-SERVER>:8000"
# Output: E:\pelog-build\installer\PELOG_Setup.exe
```

---

## 2. Test Otomatis

### 2.1 Server â€” 80 test, 250 assertion (semua hijau)

Cakupan utama:

| Area | Yang diverifikasi |
|---|---|
| Enrollment | kode valid/salah/disabled, hostname bentrok, rotasi token, **adopsi setelah re-image** (uuid dirotasi + audit), validasi payload |
| Device auth | token tidak ada/salah/nonaktif, rate limit |
| Bootstrap | data siswa (dengan field PIN), guru, mapel, config; siswa/guru/mapel nonaktif tidak ikut |
| Lookup NISN | ditemukan, tidak ditemukan (+audit `nisn_lookup_failed`), nonaktif ditolak |
| PIN | set pertama, tolak PIN lemah, panjang salah, tidak bisa set dua kali, audit `pin_set` |
| Sesi | start/heartbeat/end, idempotensi UUID, `device_busy`, `pin_not_set`, guru tanpa PIN, durasi, feedback, penutupan oleh device lain ditolak |
| Screenshot | upload sekali, idempoten, >300 KB ditolak, non-gambar ditolak, sesi tak dikenal ditolak, thumbnail dibuat |
| Sync offline | per-item results, idempoten (`skipped`), siswa tak dikenal, validasi payload |
| Stale sessions | sesi menggantung ditutup `recovery` + device dibebaskan; sesi sehat tidak tersentuh |
| Dashboard/akses | tamu diarahkan login, guru 403 pada halaman admin, registrasi publik ditutup (404) |

### 2.2 Client â€” 27 test (semua hijau)

| Area | Yang diverifikasi |
|---|---|
| PinHasher | **vektor PBKDF2 dari PHP terverifikasi di C#** (bukti kompatibilitas lintas bahasa), roundtrip, salt acak |
| PinPolicy | PIN lemah/kuat |
| LocalStore (SQLite) | kv, cache siswa/guru/mapel, status kunci PIN lokal, siklus sesi + antrean sync, antrean screenshot |
| ServerClock | offset jam server diterapkan & dipertahankan |
| ImageEncoder | downscale ke 1280 px, WebP utama, fallback JPEG |
| Integrasi E2E (server nyata) | enroll â†’ bootstrap â†’ set PIN (+verifikasi hash server di C#) â†’ start â†’ heartbeat â†’ upload screenshot WebP â†’ end â†’ sync batch (+idempotensi) â†’ token salah ditolak 401 |

---

## 3. Uji End-to-End di VM (Fase 6)

VM: Windows 10 (1920x1080), VMware Workstation 17.6.4 di `E:\New folder (34)\pelog vm.vmx`.
Kontrol: VNC (vncdotool via `scripts/vnc-driver.py`), snapshot `sebelum-install`.
Bukti visual: `docs/evidence-fase-6/` (102 tangkapan layar).

| # | Skenario | Hasil | Bukti |
|---|---|---|---|
| 1 | Install senyap via installer (UAC Alt+Y) | âœ… aplikasi, config, task, autostart terpasang | 43, 52 |
| 2 | Enrollment (kode dari dashboard) | âœ… device terdaftar di server | 44â€“46 |
| 3 | Login siswa pertama (wizard PIN + instruksi) | âœ… PIN tersimpan server (PBKDF2) | 24, 25 |
| 4 | Verifikasi PIN | âœ… | 36, 39 |
| 5 | Sesi: mapel + tujuan â†’ MULAI | âœ… device `in_use`, widget + stopwatch | 27, 49 |
| 6 | Heartbeat 60 detik di server | âœ… `last_heartbeat_at` tepat +60s | Â§4 |
| 7 | Screenshot otomatis + upload (test mode menit-1) | âœ… WebP 41,7 KB + thumb 8 KB di server | Â§4, 50 |
| 8 | Selesai + form refleksi | âœ… sesi `normal`, durasi, pemahaman, feedback tersimpan | 50, 51, Â§4 |
| 9 | Recovery/resume (kill proses â†’ start ulang) | âœ… widget lanjut (stopwatch tidak reset), heartbeat lanjut | 54, 55, 56 |
| 10 | Offline total (server mati) | âœ… banner offline, login dari cache, sesi & feedback tercatat lokal | 57â€“62 |
| 11 | Sinkron otomatis saat online kembali | âœ… sesi offline masuk server (`sync_source=offline`) + penutupan tersinkron | Â§4, 67 |
| 12 | Hardening (policy installer) | âœ… Task Manager "Access is denied", regedit diblokir | 21, 75 |
| 13 | Mode Guru (NIP) | âœ… tanpa mapel, tanpa feedback, tercatat `user_type=staff` | 70â€“72 |
| 14 | Autostart setelah reboot | âœ… kiosk terbuka otomatis | 35, 38, 94 |
| 15 | Watchdog (task 2 menit) | âœ… relaunch otomatis setelah proses dibunuh | 13, 76 |
| 16 | Adopsi perangkat setelah re-image (VM revert) | âœ… uuid dirotasi, audit `device_adopted` | 47, 48 |
| 17 | Sesi menggantung dibersihkan server (cron 5 menit) | âœ… 3 sesi ditutup `recovery`, device bebas | Â§4 |
| 18 | Mode Admin: hotkey + password + banner + degradasi aman | âœ… dialog set/verifikasi password, banner, peringatan bila suspend gagal | 84, 85, 86, 95â€“98 |
| 19 | Hardening -Apply / -Suspend (skrip) | âœ… -Apply terverifikasi di VM; -Suspend tervalidasi eksekusinya | 75, Â§4 |
| 20 | Uninstall (task & autostart dibersihkan) | â³ diuji manual saat produksi (skrip siap) | â€” |

Catatan #18â€“19: prompt UAC pada VM uji (autologon + akun tanpa password + secure desktop) tidak dapat di-approve otomatis secara konsisten oleh harness VNC. Jalur `-Apply` sempat tervalidasi penuh di VM; `-Suspend` divalidasi eksekusinya. **Klik UAC final perlu dilakukan manusia** â€” diuji sekali lagi saat deployment sekolah.

---

## 4. Bukti Data (contoh dari kampanye VM)

Sesi lengkap (siswa `0000000001`):

```
session_uuid: e1087888-c6e2-4064-b5da-0b0c29ed5a11
closed_at: 2026-09-14 17:16:48 | close_reason: normal | duration: 2 menit
comprehension_level: sangat_paham
student_feedback: "Saya belajar mengelola jaringan dan konfigurasi perangkat lab"
```

Sesi offline tersinkron:

```
85e7759f... | "Sesi kedua saat offline" | sync_source: offline | feedback tersimpan
```

Sesi recovery (simulasi mati listrik / aplikasi dibunuh):

```
e51630d7... | durasi 3 menit (termasuk jeda crash + resume) | "Sesi recovery diuji sukses"
ab23cae6... | ditutup otomatis oleh cron stale â†’ close_reason: recovery
```

Screenshot di server:

```
format: webp | size_bytes: 42750 | path: screenshots/2026/09/e1087888-....webp
thumbnail: e1087888-...-thumb.jpg (8 KB)
```

Autostart & watchdog:

```
PELOG Kiosk (Logon)     N/A    Ready
PELOG Kiosk (Watchdog)  ...    Running
```

---

## 5. Temuan Penting Saat Pengujian (semua sudah diperbaiki)

| Temuan | Perbaikan |
|---|---|
| Installer menaruh `pelog.client.json`, aplikasi membaca `pelog.json` | `DestName` diperbaiki di installer |
| Aplikasi crash saat menulis policy HKCU tanpa elevasi (ACL `Policies` menolak) â†’ arsitektur hardening diubah: **installer (elevated) yang menerapkan policy**; aplikasi hanya keyboard-blocker + MODE ADMIN via UAC | Commit `c2eda11` |
| Token DPAPI `CurrentUser` tidak terbaca lintas akun (installer vs siswa) | DPAPI `LocalMachine` |
| Data di ProgramData tidak bisa ditulis akun siswa | `icacls` grant Users Modify saat install |
| Re-image laptop â†’ enrollment ditolak `hostname_taken` selamanya | **Adopsi perangkat** (uuid dirotasi, audit) + guard 10 menit/aktif |
| Sesi menggantung menahan `in_use` di server | Perintah `pelog:close-stale-sessions` + scheduler 5 menit |
| Sesi offline/recovery lama tertahan di antrean | Sinkronisasi idle di lockscreen (60 detik) |
| Recovery tidak pernah terhubung ke UI | `TryRecoverSession()` saat aplikasi dibuka (resume atau tutup `recovery`) |

---

## 6. Batasan & Sisa Pekerjaan Sebelum Produksi

1. **Klik UAC MODE ADMIN** â€” perlu verifikasi manual sekali di laptop sekolah (harness VM tidak bisa).
2. **Screenshot produksi = menit ke-30** (config `test_mode=false`); diuji memakai test mode menit-1.
3. **Deploy server produksi** (VPS/aaPanel, SSL, cron scheduler, backup) belum dilakukan â€” menunggu keputusan sekolah.
4. **Impor data nyata** (siswa/guru) dan penyesuaian logo/teks sekolah.
5. **Uji uninstall** di mesin produksi (skrip & task sudah disiapkan).
6. **Kebijakan privasi** perlu disahkan sekolah sebelum screenshot aktif di mesin nyata.

---

## 7. Verifikasi Tambahan: Update Versi Tanpa Widget (2026-09-15)

Konteks: build baru (tanpa widget timer, tanpa dialog instance ganda, perbaikan export xlsx) dipasang di VM `pelog vm` memakai installer yang sama (GUI, diunduh via Edge di guest).

| # | Skenario | Hasil |
|---|---|---|
| 1 | Desktop selama sesi aktif tanpa widget stopwatch | Lulus - tidak ada widget; desktop bersih |
| 2 | Ikon tray selama sesi (logo, tooltip durasi) | Lulus - ikon tampil di system tray |
| 3 | Hotkey Ctrl+Alt+S membuka Refleksi lalu menutup sesi | Lulus - form muncul, SIMPAN kembali ke lockscreen |
| 4 | Watchdog (tiap 2 menit) tidak lagi memunculkan dialog "sudah berjalan" | Lulus - peluncuran ganda keluar senyap, tanpa dialog |
| 5 | Login siswa: NISN - nama/kelas - PIN - mapel/tujuan - mulai | Lulus |
| 6 | Wizard PIN pertama (wajib online) untuk siswa demo | Lulus - PIN tersimpan via server |
| 7 | Sesi tercatat server: start, tutup, feedback, pemahaman | Lulus - `usage_sessions` id=6, close_reason=normal, sangat_paham, sync_source=online |
| 8 | Device terdaftar ulang setelah DB staging di-reset | Lulus - `devices` id=9 DESKTOP-OQKA7B3 (kode enrollment baru) |

Catatan operasional penting hasil sesi ini:

1. Saat hardening aktif, di akun siswa **cmd.exe dan file .bat/.cmd diblokir** (DisableCMD=2) dan **PowerShell diblokir** (DisallowRun). Untuk pemeliharaan di akun siswa, jalankan skrip lewat **wscript (.vbs)** - lihat `scripts/vm-reset-kiosk.vbs` - atau login ke akun admin Windows.
2. **MODE ADMIN tidak dapat menangguhkan hardening saat policy aktif** karena skrip suspend memakai `powershell.exe` yang juga diblokir DisallowRun. Rekomendasi perbaikan: ganti mekanisme suspend menjadi Scheduled Task elevated yang dibuat installer (belum diimplementasikan). **[SUDAH DIPERBAIKI - lihat bagian 8]**

---

## 8. Verifikasi Fitur Baru (2026-09-15, sesi lanjutan)

Fitur yang ditambahkan/diperbaiki: nama perangkat saat enroll, mode perawatan perangkat, filter & urutan halaman Screenshot, tutup paksa sesi dari dashboard, perbaikan MODE ADMIN (Scheduled Task), heartbeat saat form refleksi, pembersihan teks UI.

| # | Skenario | Hasil |
|---|---|---|
| 1 | Enroll menyimpan label perangkat (kolom "Nama perangkat") | Lulus - unit test server |
| 2 | Ubah nama/lokasi/status perangkat dari dashboard | Lulus - DB `LAB-BL-09` + audit `device_updated` |
| 3 | Status perawatan memblokir sesi baru | Lulus - HTTP 423 `device_maintenance` + audit `session_blocked_maintenance` |
| 4 | Client menampilkan pesan + label status perawatan | Lulus - pesan merah saat MULAI + label bawah "Laptop dalam perawatan Admin IT" |
| 5 | Kembali ke Tersedia langsung bisa mulai (tanpa tunggu refresh 15 menit) | Lulus - sesi id=16 dibuat seketika |
| 6 | Filter screenshot per perangkat + pencarian nama + 4 mode urut | Lulus - UI + unit test |
| 7 | Tutup paksa sesi aktif dari dashboard (alasan `admin`) | Lulus - durasi benar, perangkat kembali `available`, audit `session_closed_admin` |
| 8 | MODE ADMIN menangguhkan hardening tanpa prompt UAC | Lulus - tanpa peringatan; probe registry `DisableTaskMgr` hilang; KEMBALI KE KIOSK menerapkan ulang |
| 9 | Form refleksi tidak memblokir heartbeat (non-modal) | Lulus - sesi tetap berjalan & tercatat (id=16, feedback tersimpan) |
| 10 | Pembersihan teks UI (login, footer, catatan penyimpanan) | Lulus - halaman login bersih, catatan penyimpanan dihapus |

### 8.1 Hook shutdown/restart + tray + mode admin desktop (2026-09-15, sesi ketiga)

| # | Skenario | Hasil |
|---|---|---|
| 11 | Sesi aktif ditutup otomatis saat **Restart OS** (tombol Start > Restart) | Lulus - sesi `id=25` tercatat `close_reason=shutdown`, `closed_at` benar; bukti log lokal `C:\ProgramData\PELOG\data\shutdown.log` |
| 12 | Sesi aktif ditutup otomatis saat aplikasi ditutup paksa installer (Restart Manager) | Lulus - `id=24` tercatat `close_reason=shutdown` |
| 13 | Sinkronisasi menerapkan penutupan sesi yang sudah ada di server (idempoten) | Lulus - `id=21` tertutup `normal` + feedback via sync klien |
| 14 | Ikon tray dihilangkan saat sesi berjalan | Lulus - area tray bersih (hanya ikon sistem) |
| 15 | MODE ADMIN: jendela kiosk disembunyikan, desktop bebas dipakai | Lulus - desktop + taskbar dapat digunakan |
| 16 | MODE ADMIN: tombol pintasan **Buka CMD** berfungsi | Lulus - jendela Command Prompt terbuka & dapat diketik |
| 17 | MODE ADMIN: tombol pintasan **Pengaturan** berfungsi | Lulus - aplikasi Windows Settings terbuka |
| 18 | KEMBALI KE KIOSK menerapkan ulang kebijakan + menampilkan kiosk | Lulus - kiosk kembali, hardening aktif via task |
| 19 | Ketik `cmd` pada address bar Explorer saat MODE ADMIN (keluhan pengguna) | Lulus - Command Prompt terbuka; tidak ada lagi pesan "Accessing the resource 'cmd' has been disallowed" |
| 20 | Run dialog (Win+R) + `cmd` saat MODE ADMIN | Lulus - dialog terbuka, cmd berjalan |
| 21 | Shell (explorer) dimuat ulang otomatis setelah penangguhan (anti-cache kebijakan) | Lulus - shell baru tanpa kebijakan lama; tersedia juga tombol manual "Muat Ulang Shell" |
| 22 | "Keluar Aplikasi" melepas penguncian (mesin kembali normal) | Lulus - kebijakan ditangguhkan saat keluar aplikasi |
| 23 | Hardening dipasang ulang otomatis setiap aplikasi kiosk dijalankan | Lulus - `Last Run Time` task `BALILogHardeningApply` = waktu start aplikasi, `Last Result: 0` |
| 24 | Sesi murid aktif: Run dialog (Win+R) + `cmd` | Lulus - dialog terbuka, Command Prompt berjalan normal |
| 25 | Sesi murid aktif: Task Manager (Ctrl+Shift+Esc) | Lulus - Task Manager terbuka |
| 26 | Sesi berakhir -> penguncian kiosk dipasang ulang otomatis | Lulus - `Last Run Time` task `BALILogHardeningApply` = detik sesi berakhir (3:35:26), `Last Result: 0`; kiosk kembali terkunci |
| 27 | Mode dinamis: layar kunci TERKUNCI, sesi murid/guru BEBAS penuh | Lulus - sesi `id=40` ditutup `normal` + feedback; semua alur di atas konsisten (mekanisme sama untuk semua tipe pengguna) |

Catatan teknis: task elevated dibuat lewat XML (`schtasks /TR` tidak bisa menangani path ber-spasi + kutip - "Invalid argument/option"). Task: `BALILogHardeningSuspend` / `BALILogHardeningApply` (InteractiveToken + HighestAvailable, tanpa trigger), dibuat installer saat opsi "Terapkan penguncian kiosk" dicentang.

Hook shutdown ganda: `SystemEvents.SessionEnding` + pesan Windows `WM_QUERYENDSESSION`/`WM_ENDSESSION` pada jendela runtime (idempoten, tidak pernah menghambat shutdown). Urutan: tulis penutupan ke DB lokal -> upaya lapor server maks 2 detik -> sisa antrean tersinkron saat aplikasi jalan kembali.

Tes otomatis: **98 tes server** (15 baru: label perangkat, blokir perawatan, admin perangkat, tutup sesi, filter screenshot) + **27 tes client** - semuanya hijau.
3. Heartbeat terjeda selama dialog Refleksi terbuka (modal memblokir UI thread). Aman karena penutup sesi menggantung memakai batas 15 menit; endpoint end tetap idempoten.

Batasan yang tersisa sama dengan bagian 6.

---

## 9. Intersep Shutdown/Restart dengan Refleksi Wajib (2026-09-15, sesi keempat)

Fitur: shutdown/restart saat sesi berjalan ditahan (`WM_QUERYENDSESSION` -> FALSE) + alasan tampil di layar Windows; form refleksi WAJIB (tanpa tombol lewati) tampil di desktop setelah pengguna memilih Cancel/Batal pada layar Windows; setelah "SIMPAN & MATIKAN" aplikasi mematikan komputer (`ExitWindowsEx(EWX_POWEROFF)` + hak `SE_SHUTDOWN_NAME`); "BATAL" menghentikan percobaan shutdown (sesi lanjut); **log off TIDAK diintersep**; tanda `shutdown_pending` di DB lokal memastikan sesi tetap tercatat `shutdown` bila proses dibunuh paksa.

| # | Skenario | Hasil |
|---|---|---|
| 28 | Shutdown saat sesi aktif (`shutdown /s /t 0`) | Lulus - layar Windows "Closing 1 app and shutting down" menampilkan alasan "PELOG: klik Cancel/Batal, lalu isi refleksi belajar supaya laptop bisa dimatikan." (sesi id=46) |
| 29 | Klik Cancel pada layar Windows | Lulus - kembali ke desktop; form "Refleksi Sebelum Mematikan" tampil di atas (TopMost + re-assert Z-order tiap 700 md) |
| 30 | Isi refleksi + "SIMPAN & MATIKAN" | Lulus - sesi `id=50` `close_reason=shutdown` + feedback + pemahaman `paham`; **komputer mati otomatis** (VM power-off; terbukti dari daftar VM - pelog vm hilang - dan koneksi VNC terputus) |
| 31 | Tombol "BATAL" pada form | Lulus - percobaan shutdown dibatalkan; sesi `id=52` tetap berjalan (heartbeat lanjut); log `alasan=feedback-cancelled` |
| 32 | Log off (`shutdown /l`) | Lulus - TANPA form refleksi; sesi `id=51` ditutup otomatis `close_reason=shutdown` |
| 33 | Regresi alur normal Ctrl+Alt+S | Lulus - form mode normal ("SIMPAN KUNCI LAPTOP", tanpa Batal); sesi `id=52` ditutup `normal` + feedback |
| 34 | Shutdown paksa dari layar Windows ("Shut down anyway") | Lulus - sesi tetap ditutup `shutdown` (id=43; jalur WM_ENDSESSION + tanda `shutdown_pending`, juga diuji unit) |
| 35 | Sesuaikan verifikasi: akun kiosk bersandi -> layar "Sign in" setelah Cancel | Catatan - pada VM, akun goldpump bersandi sehingga kembali ke desktop butuh sandi; disarankan akun kiosk tanpa sandi agar siswa kembali dengan satu klik |
| 36 | Auto power-off gagal (degradasi, sebelum fix struct LUID) | Lulus - pesan "Refleksi tersimpan. Silakan matikan laptop dengan menu Power atau tombol power." + log `alasan=poweroff-manual` + kiosk kembali terkunci |

Temuan & perbaikan penting sesi ini:

1. `ShutdownBlockReasonCreate` ada di **user32.dll** (header winuser.h), bukan shell32.dll - deklarasi salah menyebabkan `EntryPointNotFoundException` (dialog crash .NET) tepat saat penolakan shutdown. Diperbaiki + dibungkus try/catch (API opsional; pemblokiran tetap jalan lewat nilai balik FALSE).
2. Struct `TOKEN_PRIVILEGES` dengan field `long Luid` salah alignment di x64 (native: LUID 4-byte aligned) sehingga `AdjustTokenPrivileges` gagal dan auto power-off tidak jalan. Diperbaiki memakai `struct Luid { uint LowPart; int HighPart; }`.
3. Layar "menutup aplikasi" milik Windows selalu tampil saat ada penolakan shutdown dan tidak dapat ditimpa oleh jendela aplikasi (topmost tidak menang). Alur baku: pengguna klik Cancel/Batal pada layar itu -> form aplikasi tampil. Teks alasan di layar Windows memberi instruksi langsung.
4. Log diagnostik `C:\ProgramData\PELOG\data\shutdown.log` kini mencatat tiap tahap: `blocked-feedback`, `feedback-cancelled`, `feedback-saved`, `poweroff-manual`, `shutdown`.
5. Tes otomatis bertambah 4 tes recovery untuk tanda `shutdown_pending` -> **31 tes client** (semuanya hijau).

Batasan (batas OS, bukan bug aplikasi):

- `shutdown /f` (paksa) dan tahan tombol power 5 detik tidak dapat diintersep; sesi tetap tercatat `shutdown` lewat hook/tanda.
- Bila pengguna memilih "Shut down anyway", refleksi terlewat (sesi tetap ditutup `shutdown`).
