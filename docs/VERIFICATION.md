# VERIFICATION — BALI-LOG

Laporan verifikasi menyeluruh. Diperbarui: 15 September 2026 (Fase 6 selesai di VM).
Semua perintah dijalankan dari root repo `balilog/` kecuali disebut lain.

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
$code = (php server\artisan balilog:enrollment-code | Select-String "BLG-").Matches[0].Value
$env:BALILOG_E2E_CODE = $code
dotnet test Balilog.sln

# 3) Build aplikasi + installer
.\scripts\build-client.ps1
.\scripts\build-installer.ps1 -BaseUrl "http://<IP-SERVER>:8000"
# Output: E:\balilog-build\installer\BALI-LOG_Setup.exe
```

---

## 2. Test Otomatis

### 2.1 Server — 80 test, 250 assertion (semua hijau)

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

### 2.2 Client — 27 test (semua hijau)

| Area | Yang diverifikasi |
|---|---|
| PinHasher | **vektor PBKDF2 dari PHP terverifikasi di C#** (bukti kompatibilitas lintas bahasa), roundtrip, salt acak |
| PinPolicy | PIN lemah/kuat |
| LocalStore (SQLite) | kv, cache siswa/guru/mapel, status kunci PIN lokal, siklus sesi + antrean sync, antrean screenshot |
| ServerClock | offset jam server diterapkan & dipertahankan |
| ImageEncoder | downscale ke 1280 px, WebP utama, fallback JPEG |
| Integrasi E2E (server nyata) | enroll → bootstrap → set PIN (+verifikasi hash server di C#) → start → heartbeat → upload screenshot WebP → end → sync batch (+idempotensi) → token salah ditolak 401 |

---

## 3. Uji End-to-End di VM (Fase 6)

VM: Windows 10 (1920x1080), VMware Workstation 17.6.4 di `E:\New folder (34)\balilog vm.vmx`.
Kontrol: VNC (vncdotool via `scripts/vnc-driver.py`), snapshot `sebelum-install`.
Bukti visual: `docs/evidence-fase-6/` (102 tangkapan layar).

| # | Skenario | Hasil | Bukti |
|---|---|---|---|
| 1 | Install senyap via installer (UAC Alt+Y) | ✅ aplikasi, config, task, autostart terpasang | 43, 52 |
| 2 | Enrollment (kode dari dashboard) | ✅ device terdaftar di server | 44–46 |
| 3 | Login siswa pertama (wizard PIN + instruksi) | ✅ PIN tersimpan server (PBKDF2) | 24, 25 |
| 4 | Verifikasi PIN | ✅ | 36, 39 |
| 5 | Sesi: mapel + tujuan → MULAI | ✅ device `in_use`, widget + stopwatch | 27, 49 |
| 6 | Heartbeat 60 detik di server | ✅ `last_heartbeat_at` tepat +60s | §4 |
| 7 | Screenshot otomatis + upload (test mode menit-1) | ✅ WebP 41,7 KB + thumb 8 KB di server | §4, 50 |
| 8 | Selesai + form refleksi | ✅ sesi `normal`, durasi, pemahaman, feedback tersimpan | 50, 51, §4 |
| 9 | Recovery/resume (kill proses → start ulang) | ✅ widget lanjut (stopwatch tidak reset), heartbeat lanjut | 54, 55, 56 |
| 10 | Offline total (server mati) | ✅ banner offline, login dari cache, sesi & feedback tercatat lokal | 57–62 |
| 11 | Sinkron otomatis saat online kembali | ✅ sesi offline masuk server (`sync_source=offline`) + penutupan tersinkron | §4, 67 |
| 12 | Hardening (policy installer) | ✅ Task Manager "Access is denied", regedit diblokir | 21, 75 |
| 13 | Mode Guru (NIP) | ✅ tanpa mapel, tanpa feedback, tercatat `user_type=staff` | 70–72 |
| 14 | Autostart setelah reboot | ✅ kiosk terbuka otomatis | 35, 38, 94 |
| 15 | Watchdog (task 2 menit) | ✅ relaunch otomatis setelah proses dibunuh | 13, 76 |
| 16 | Adopsi perangkat setelah re-image (VM revert) | ✅ uuid dirotasi, audit `device_adopted` | 47, 48 |
| 17 | Sesi menggantung dibersihkan server (cron 5 menit) | ✅ 3 sesi ditutup `recovery`, device bebas | §4 |
| 18 | Mode Admin: hotkey + password + banner + degradasi aman | ✅ dialog set/verifikasi password, banner, peringatan bila suspend gagal | 84, 85, 86, 95–98 |
| 19 | Hardening -Apply / -Suspend (skrip) | ✅ -Apply terverifikasi di VM; -Suspend tervalidasi eksekusinya | 75, §4 |
| 20 | Uninstall (task & autostart dibersihkan) | ⏳ diuji manual saat produksi (skrip siap) | — |

Catatan #18–19: prompt UAC pada VM uji (autologon + akun tanpa password + secure desktop) tidak dapat di-approve otomatis secara konsisten oleh harness VNC. Jalur `-Apply` sempat tervalidasi penuh di VM; `-Suspend` divalidasi eksekusinya. **Klik UAC final perlu dilakukan manusia** — diuji sekali lagi saat deployment sekolah.

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
ab23cae6... | ditutup otomatis oleh cron stale → close_reason: recovery
```

Screenshot di server:

```
format: webp | size_bytes: 42750 | path: screenshots/2026/09/e1087888-....webp
thumbnail: e1087888-...-thumb.jpg (8 KB)
```

Autostart & watchdog:

```
BALI-LOG Kiosk (Logon)     N/A    Ready
BALI-LOG Kiosk (Watchdog)  ...    Running
```

---

## 5. Temuan Penting Saat Pengujian (semua sudah diperbaiki)

| Temuan | Perbaikan |
|---|---|
| Installer menaruh `balilog.client.json`, aplikasi membaca `balilog.json` | `DestName` diperbaiki di installer |
| Aplikasi crash saat menulis policy HKCU tanpa elevasi (ACL `Policies` menolak) → arsitektur hardening diubah: **installer (elevated) yang menerapkan policy**; aplikasi hanya keyboard-blocker + MODE ADMIN via UAC | Commit `c2eda11` |
| Token DPAPI `CurrentUser` tidak terbaca lintas akun (installer vs siswa) | DPAPI `LocalMachine` |
| Data di ProgramData tidak bisa ditulis akun siswa | `icacls` grant Users Modify saat install |
| Re-image laptop → enrollment ditolak `hostname_taken` selamanya | **Adopsi perangkat** (uuid dirotasi, audit) + guard 10 menit/aktif |
| Sesi menggantung menahan `in_use` di server | Perintah `balilog:close-stale-sessions` + scheduler 5 menit |
| Sesi offline/recovery lama tertahan di antrean | Sinkronisasi idle di lockscreen (60 detik) |
| Recovery tidak pernah terhubung ke UI | `TryRecoverSession()` saat aplikasi dibuka (resume atau tutup `recovery`) |

---

## 6. Batasan & Sisa Pekerjaan Sebelum Produksi

1. **Klik UAC MODE ADMIN** — perlu verifikasi manual sekali di laptop sekolah (harness VM tidak bisa).
2. **Screenshot produksi = menit ke-30** (config `test_mode=false`); diuji memakai test mode menit-1.
3. **Deploy server produksi** (VPS/aaPanel, SSL, cron scheduler, backup) belum dilakukan — menunggu keputusan sekolah.
4. **Impor data nyata** (siswa/guru) dan penyesuaian logo/teks sekolah.
5. **Uji uninstall** di mesin produksi (skrip & task sudah disiapkan).
6. **Kebijakan privasi** perlu disahkan sekolah sebelum screenshot aktif di mesin nyata.
