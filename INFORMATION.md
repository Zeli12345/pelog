# PELOG — INFORMATION.md (Sesi Baru? Baca file ini dulu)

> **Dokumen ini = "otak" proyek PELOG.** Tuples semua yang perlu diketahui agar sesi
> baru (agen/manusia) langsung paham konteks, konvensi, prosedur deploy, dan Jebakan
> historis — **tanpa perlu Anda menjelaskan ulang**.
>
> Pasangan: `docs/DEPLOY-PRODUCTION.md` (panduan deploy), `docs/VERIFICATION.md` (laporan
> verifikasi), `releases/README.md` (riwayat rilis + SHA), `LOCAL-SECRETS.md`
> (**tidak** ter-commit — berisi kredensial; lihat catatan di bawah).
>
> Dibuat: **21 Okt 2026** · Versi sistem saat dokumen ini diperbarui: **1.0.3** (22 Okt 2026)

---

## 0. Aturan main sesi baru (30 detik)

1. Baca bagian 1–3 (apa ini, stack, peta repo), lalu bagian yang relevan dengan task.
2. **Jangan menebak** — baca kode sebelum mengubah (repo ini punya banyak jebakan; lihat §15).
3. Bahasa **UI/pesan/commit = Indonesia**. Kodenama/istilah teknis boleh Inggris.
4. TDD: tulis tes gagal dulu, lihat gagal, baru implementasi (butuh `php artisan test` / `dotnet test`).
5. **Jangan pernah** `git push --force`, jangan menyentuh situs lain di server aaPanel.
6. Setiap selesai deploy ke produksi: **hapus runner ops** (harus balas 404) + bersihkan `_up`/tmp.
7. Kredensial ada di `LOCAL-SECRETS.md` (file lokal, gitignored) — jangan pernah menulis
   rahasia ke file yang ter-commit (repo GitHub **publik**).

---

## 1. Apa itu PELOG

- **PELOG** (sebelumnya BALI-LOG) = sistem **monitoring aktivitas laptop** untuk
  **SMK Negeri 1 Mas Ubud** (motto *Kriya Kencana Raksa*), domain `pelog.smkn1mas.sch.id`.
- 3 komponen: **Server** (Laravel 12, dashboard + API), **Klien Kiosk** (Windows, .NET 8
  WinForms, penguncian laptop), **Installer** (Inno Setup) + **Alat Perbaikan**
  (`PELOG-Revert.exe`).
- Alur utama: **IT enroll laptop** (kode enrollment) → **siswa/guru login di layar
  kunci** (NISN + tanggal lahir, atau NIP) → **sesi tercatat** (mapel/tujuan/durasi) →
  **screenshot berkala** → **dashboard**看见了 statistik, laporan, ekspor, audit.
- Skema perangkat: tiada PIN, tiada reflection; verifikasi siswa bisa **offline**
  (cache lokal terenkripsi). Kontrol orang tua/keluarga **tidak ada** — ini sistem
  dipakai sekolah, bukan ransomware.

## 2. Stack & versi

| Bagian | Teknologi | Versi saat ini |
|---|---|---|
| Server | Laravel + PHP | 12.x / **PHP 8.4** (FPM), MySQL/MariaDB |
| Dashboard | Blade + Tailwind + Alpine.js | Vite (`npm run build` → `server/public/build`) |
| Klien | .NET | **8** (net8.0-windows), WinForms, self-contained single-file (169,8 MB publish) |
| Installer | Inno Setup | 6 (`ISCC.exe`), ~33 detik compile, ~51,9 MB |
| Tool perbaikan | .NET 8 WinForms | `PELOG-Revert.exe` 68,3 MB (self-contained, UAC admin) |
| Rilis | - | **1.0.3** (AppInfo, csproj, `pelog-setup.iss`, setelan server) |

Lokasi build (dev machine): output **`E:\pelog-build`** (`publish/`, `installer/`,
`client/bin/`), cache `E:\pelog-cache` (nuget/composer/npm/temp) — diatur `scripts/env.ps1`.

## 3. Peta repo

```
balilog/
├─ server/            Laravel: app/{Http,Models,Support,Enums,Policies,Console,Services,Imports,Exports},
│                     routes/{web,api,auth,console}.php, database/{migrations,seeders,factories},
│                     resources/views (Blade), tests/{Feature,Unit}, config/filesystems.php
├─ client/            PelogKiosk (app WinForms) · PelogKiosk.Core (library) ·
│                     PelogKiosk.Tests (xUnit) · PelogRevert (alat perbaikan) · PelogRevert.Tests
├─ installer/         pelog-setup.iss · hardening.ps1 · apply-update.ps1 · unlock-admin.ps1 ·
│                     README-OPS.txt · pelog.client.json (di-generate build) · enrollment.txt (rahasia, gitignored)
├─ scripts/           build-client.ps1 · build-installer.ps1 · build-revert.ps1 · dev-*.ps1 · vnc-driver.py
├─ releases/          installer rilis + README.md (SHA) + tools/PELOG-Revert.exe
├─ docs/              DEPLOY-PRODUCTION.md · VERIFICATION.md · nginx/ · evidence-fase-6/ · design-preview*/
├─ assets/            logo_sekolah.png (dipakai klien & dashboard)
└─ Pelog.sln
```

## 4. Arsitektur ringkas

```
KLIEN (Windows)  ──HTTPS──►  SERVER (Nginx → PHP-FPM → Laravel)
  Program.cs → LockscreenForm / EnrollForm / ActiveSessionRuntime
  Core: Api, Data(LocalStore SQLite), Services(Session/Sync/Update/Metrics/Idle), Security(DPAPI+AES-GCM)
  Installer menulis pelog.json + enrollment.txt; autostart HKLM Run + task ONLOGON + watchdog
                    ◄── server.push: heartbeat, sesi, screenshot, metrik
DASHBOARD (admin/operator) → API v1 (Bearer token per perangkat) + halaman web
```

- Data lokal klien: `C:\ProgramData\PELOG\` (`pelog.json`, `enrollment.txt`, `data/local.db`,
  `data/screenshots/`, `data/updates/`, `kiosk.log`).
- Instalasi aplikasi: `C:\Program Files\PELOG Kiosk\`.
- Device token = acak 256-bit, **hash SHA-256** di server (`devices.device_token_hash`).
- `device_uuid` = GUID sekali buat (pencocokan perangkat saat enroll/adopsi/restore).

## 5. Peran & otorisasi (server)

| Peran | Nilai | Label UI | Kewenangan |
|---|---|---|---|
| Admin Utama | `admin_utama` | "Admin" | Semua; satu-satunya yang bisa mengelola akun admin; akun `admin_utama` **terkunci** dari UI (dikelola via Profil) |
| Sub Admin | `sub_admin` | "Admin" | Semua data/fitur **kecuali** mengelola akun admin; boleh kelola akun Viewer |
| Viewer | `viewer` | "Viewer" | Baca-saja: monitoring + ekspor + GET master data; **tanpa** aksi tulis |

- Ditegakkan berlapis: alias middleware `admin` → `EnsureAdmin` (`isAdmin()`) → grup rute,
  `UserPolicy` (App\Policies\UserPolicy) → `Gate::authorize()` di controller → `FormRequest::authorize()`.
- Label admin sengaja **sama** ("Admin") di seluruh UI (permintaan user).
- Menu dashboard: "Pemantauan" (semua), "Master Data" (semua, read-only untuk Viewer),
  "Sistem" (hanya Admin: Pengguna, Audit, Pengaturan).
- Tombol aksi disembunyikan (bukan sekadar 403) untuk non-admin: `@if ($isAdmin)` di
  students/staff/subjects/devices/sessions.

## 6. Server (Laravel) — peta rinci

**Rute web (routes/web.php)**: `/dashboard`, `/devices`, `/sessions`, `/reports(+export)`,
`/screenshots(+thumb/file)`, `/students`, `/staff`, `/subjects` (baca, grup `auth`) ·
grup `admin`: `/audit`, `/settings(+enrollment-code,+client-installer)`, `/users` (CRUD +
`/users/{user}/toggle`), CRUD students/staff/subjects, bulk/restore, `POST /devices/{id}/request-screenshot`,
`POST /sessions/{id}/close`, `GET /downloads/repair-tool` (khusus Admin).
Lainnya: `/downloads/client-setup` (publik, throttle 30/menit — dipakai agen SYSTEM),
`/login` (registrasi publik ditutup), `/profile`.

**API v1 (routes/api.php)**: `GET /health`, `GET /version`, `POST /devices/enroll` (throttle 10/menit),
`GET /bootstrap`, `GET /students/{nisn}`, `POST /sessions/start|heartbeat|end`,
`POST /sessions/{uuid}/screenshot`, `POST /sync/sessions`, `GET /app/latest`, `GET /app/installer`.
Envelope respons: `{ok, data, error:{code,message,details}, server_time}`.
Rate limit: device 180/menit, enroll 10/menit, login 5 percobaan.

**Setelan (tabel `settings`, key-value JSON)** — kunci penting:
`screenshot_enabled`, `screenshot_minute` (15 = interval), `image_format`, `webp_quality` (70),
`jpeg_quality` (60), `max_width` (1280), `retention_days` (0=permanen), `disk_budget_gb` (20),
`stale_session_minutes` (5), `bootstrap_refresh_minutes` (15), `device_online_window_seconds` (300),
`idle_shutdown_minutes` (90), `single_active_session`, `enrollment_code_hash`, `enrollment_code`
(plaintext, tampil di Pengaturan + tombol Salin), `school_name`, `school_motto`,
`app_version` (1.0.3), `app_installer_file|sha256|size|notes|mandatory`, `app_updater_enabled`,
`app_update_check_hours` (6), `client_latest_version`, `client_installer_sha256|size|notes|uploaded_at`.

**Perintah artisan**: `pelog:enrollment-code` (buat kode baru), `pelog:close-stale-sessions`
(scheduler tiap 5 menit), `pelog:release-app <path> <versi> [--notes] [--mandatory]`
(Simpan ke `storage/app/private/releases/` + setelan `app_*`).

**Scheduler**: `routes/console.php`; cron aaPanel tiap menit:
`runuser -u www -- bash -lc 'cd /www/wwwroot/pelog.smkn1mas.sch.id/server && php artisan schedule:run >> storage/logs/scheduler.log 2>&1'`

**Screenshot**: kolom `content_hash` (sha256) → unggahan identik dengan screenshot terakhir
sesi yang sama dalam **5 menit** digabung (200 idempoten, audit `screenshot_deduplicated`).
Klien: `ScreenshotPolicy.MinSpacing = 90 detik` + pending-aware (tidak capture ganda).
UI: pagination per halaman (10/25/50/100), waktu sampai **detik**.

**Durasi**: `App\Support\Duration::human()` → "45 menit" / "1 jam" / "1 jam 5 menit"
(ekspor CSV/XLSX tetap angka menit). Dipakai di overview, devices/show, reports, sessions.

**Audit**: tabel `audit_logs` (action, entity, metadata JSON, actor, ip) — enroll, sesi,
screenshot, perangkat, pengaturan, kode enrollment, `user_created|updated|activated|deactivated|deleted`.

**Impor/ekspor**: `StudentsImport`/`StaffImport` (CSV/XLSX, NISN/NIP unik, tanggal lahir wajib),
`SessionsExport` (CSV + XLSX, anti formula-injection).

## 7. Klien kiosk (.NET 8) — peta rinci

- `Program.cs`: mutex single-instance → `AppServices.Create()` → token device → kalau
  **tidak ada token** → `EnrollForm` (dialog, ✕ = keluar; **tahap ini TIDAK menegakkan
  policy**) → kalau token → `LockscreenForm`.
- `LockscreenForm`: layar kunci (jam WITA, status server, mode siswa/guru, tombol Matikan,
  Wi-Fi, admin hotkey **Ctrl+Alt+Shift+B**, tutup sesi **Ctrl+Alt+S**), `EnterSessionMode`
  (suspend hardening saat sesi) → `ActiveSessionRuntime` (windowless: heartbeat 60s,
  sync 30s, screenshot per interval, idle check 60s, tutup sesi, shutdown saat admin menutup).
- `ScreenCoverForm`: penutup gelap untuk **semua monitor non-utama** saat layar kunci
  (WS_EX_NOACTIVATE, tidak merebut fokus).
- `KioskHardening`: Apply/Suspend lewat **Scheduled Task elevated** (`PelogHardeningApply/Suspend`)
  karena `DisallowRun` memblokir powershell saat aktif; `Suspend()` juga reload Explorer.
- `KioskPolicy` (Core): definisi tunggal policy HKCU (cerminan `hardening.ps1 -Apply`),
  dipakai kiosk **dan** PELOG-Revert.
- `SyncService` (antrean offline, idempoten), `UpdateService` (cek ≤6 jam, unduh, verifikasi
  SHA-256, staging + `update.json`, `IsStagingIntact`), `SystemMetrics` (CPU/RAM/GPU + nama CPU),
  `IdleDetector` (GetLastInputInfo), `ScreenCapture` (VirtualScreen = semua monitor),
  `SelfWipeService` (hapus diri saat perangkat dicabut), `SessionManager` (recover 10 menit).
- Keamanan data lokal: `TokenProtector` (DPAPI) + `StudentDataProtector` (AES-GCM, master key
  dilindungi DPAPI + entropi mesin).
- `LocalStore` (SQLite): cache siswa/staf/mapel, sesi lokal, KV (token, device_uuid, staging).

### 7.1 Lapisan anti "laptop terkunci" (hasil perbaikan 15 Okt 2026)
1. **Policy tidak lagi dipasang saat instalasi** — hanya setelah perangkat terdaftar (layar kunci).
2. Kiosk **membersihkan policy** saat: tahap enroll, keluar, dan **startup gagal**
   (langsung HKCU; bila ACL menolak → fallback task elevated `PelogHardeningSuspend`).
3. **Task `PelogParole`** (ONLOGON +2 menit): bila kiosk tidak muncul dalam **10 menit**,
   policy dilepas otomatis (log `C:\Users\Public\pelog-parole.log`).
4. **`PELOG-Revert.exe`** — alat manual untuk laptop yang sudah terkunci (lihat §9).

## 8. Installer (Inno Setup `installer/pelog-setup.iss`)

- AppId tetap (`{8F1A2C64-…}`) agar bisa upgrade; AppVersion = 1.0.3 (harus **selalu sama**
  dengan `AppInfo.Version` + csproj).
- Yang dipasang: hasil publish → `{app}`; `pelog.json` + `enrollment.txt` → `C:\ProgramData\PELOG`;
  `unlock-admin.ps1`, `hardening.ps1`, `apply-update.ps1`, `README-OPS.txt` → `{app}`.
- Autostart: **HKLM Run `PelogKiosk`** (semua user) + task `PELOG Kiosk (Logon)` + task
  `PELOG Kiosk (Watchdog)` (tiap 2 menit) + task `PelogAutoUpdate` (SYSTEM, boot+2 menit) +
  task `PelogSelfWipe` (manual) + **`PelogParole`** (ONLOGON+2m) + (opsional) task
  Apply/Suspend untuk mode admin.
- Varian: **publik** (tanpa kode, untuk GitHub) dan **berkode** (untuk server/auto-update).
  Build: `.\scripts\build-installer.ps1 -BaseUrl "https://pelog.smkn1mas.sch.id" [-EnrollmentCode "BLG-XXXX-XXXX"]`.
- `enrollment.txt` **gitignored** (rahasia).

## 9. `PELOG-Revert.exe` (alat perbaikan laptop terkunci)

- Sumber: `client/PelogRevert/` (WinForms, `requireAdministrator`, 68,3 MB), build
  `.\scripts\build-revert.ps1` → `releases\tools\PELOG-Revert.exe` + `E:\pelog-build\...\publish\`.
- Fungsi: hentikan kiosk (2 ronde) → lepas policy registry (HKCU + semua hive user yang
  ter-muat) → hapus 7 task + autostart HKLM → muat ulang Explorer → (opsional "total")
  jalankan uninstaller senyap + hapus `C:\ProgramData\PELOG` & folder aplikasi. Log: `C:\Users\Public\pelog-revert.log`.
- Di dashboard: **Pengaturan → kartu "Installer Client"** → tautan kecil "Alat perbaikan laptop
  (PELOG-Revert.exe)" (khusus Admin; tautan hanya muncul bila berkas ada di
  `storage/app/private/tools/PELOG-Revert.exe`; rute `GET /downloads/repair-tool`).

## 10. Produksi — lingkungan & prosedur

- **URL**: https://pelog.smkn1mas.sch.id (Nginx + PHP 8.4-FPM + MySQL lokal).
- **App path**: `/www/wwwroot/pelog.smkn1mas.sch.id/server` (webroot `server/public`).
- **Cron**: 1 menit (lihat §6).
- **Nginx**: WAJIB ada override `fastcgi_intercept_errors off;` (file `docs/nginx/pelog_api_errors.conf`,
  sudah dipasang di include extension situs) — tanpa itu, 404 API jadi pesan 405 nginx.
- **Panel aaPanel**: `https://103.207.99.147:37532` → perlu **entrance** (rotasi, ada di
  `LOCAL-SECRETS.md`) → login → sesi path `apsess_XXXX` (**API wajib pakai path sesi** +
  `x-http-token` + cookie + **User-Agent browser** (curl UA biasa kini 404)).

### 10.1 Teknik "ops runner" (satu-satunya cara shell di server)
1. Buat `server/public/_pelog_ops_<acak>.php` via panel API `POST {base}/v2/files?action=CreateFile`
   lalu `?action=SaveFileBody` (isi = runner PHP; kuncinya di `LOCAL-SECRETS.md`).
2. Jalankan perintah: `POST https://pelog.smkn1mas.sch.id/_pelog_ops_xxx.php?k=<KUNCI>` dengan
   `--data-urlencode "cmd@C:\path\file.sh"` (isi file bash).
3. Unggah berkas besar: `?k=…&up=1&name=X&idx=N` (chunk 8 MB, body biner) lalu
   `?k=…&up=1&join=1&name=X&parts=N&dest=/path/tujuan` (verifikasi SHA-256).
4. **Selalu hapus runner** (`rm -f public/_pelog_ops_*.php`) → konfirmasi balas **404**.

### 10.2 Deploy perubahan server (rutas)
Upload tarball (app/ routes/ resources/views/ database/ bootstrap/) → ekstrak di `server/` →
`php artisan migrate --force` (bila ada migrasi) → `php artisan optimize:clear && php artisan optimize`
→ verifikasi → **hapus runner**.

### 10.3 Publikasikan rilis client
1. Build client + installer (berkode & publik).
2. Unggah installer ke server (chunk) → `php artisan pelog:release-app /tmp/x.exe 1.0.3 --notes="…"`
   → salin ke `storage/app/private/client/PELOG_Setup.exe` (disajikan `/downloads/client-setup`).
3. Perbarui setelan `client_*` (sha256, size, notes, uploaded_at).
4. Verifikasi: `GET /api/v1/version` (sha/size cocok) + `HEAD /downloads/client-setup` (Content-Length).
5. GitHub Release: ganti aset dengan installer **publik**.

### 10.4 Auto-update (sudah terbukti bekerja 1.0.0→1.0.1)
Kiosk cek ≤6 jam → unduh + verifikasi SHA → staging (`updates/` + `update.json`) → task SYSTEM
memasang saat boot (+2 mnt) dengan `/VERYSILENT /NORESTART` (+`restartreplace`, `/NOCLOSEAPPLICATIONS`)
→ retry 3× boot. `session.active` sebagai gerbang (tidak memasang saat sesi aktif).
**Penting:** versi yang sama tidak memicu unduhan ulang → untuk memaksa pembaruan ke perangkat
yang sudah 1.0.2, **naikkan ke 1.0.3**.

## 11. Git & GitHub

- Repo: `https://github.com/Zeli12345/pelog` (**publik**), branch `main`,_TOKEN ada di `LOCAL-SECRETS.md`.
- Push: `git -c "http.extraheader=AUTHORIZATION: Basic <base64(user:token)>" push origin main`
  (jangan pernah commit secret; `installer/enrollment.txt` & `.env` sudah gitignored).
- Commit message bahasa Indonesia, awalan umum: `feat/fix/docs/chore`.
- Rilis: tag + GitHub Release dengan aset installer **publik** (tanpa kode enrollment).

## 12. Build & test

```powershell
# Server (test DB lokal MySQL pelog_test)
cd server; php artisan test                 # 166 tes / 696 asertasi
cd server; vendor\bin\phpunit               # output lebih bersih (deprecation PHP 8.5 muncul di artisan test)

# Klien
dotnet test client\PelogKiosk.Tests\PelogKiosk.Tests.csproj -c Release      # 56 tes
dotnet test client\PelogRevert.Tests\PelogRevert.Tests.csproj -c Release    # 3 tes

# Build
.\scripts\build-client.ps1                  # → E:\pelog-build\publish (169,8 MB)
.\scripts\build-installer.ps1 [-EnrollmentCode "BLG-XXXX-XXXX"]   # → E:\pelog-build\installer\PELOG_Setup.exe (51,9 MB)
.\scripts\build-revert.ps1                  # → releases\tools\PELOG-Revert.exe (68,3 MB)
cd server; npm run build                    # aset Vite (wajib bila view/klass Tailwind berubah!)
```

> ⚠️ `npm run build` wajib tiap kali kelas CSS/Tailwind baru dipakai di Blade — kalau
> tidak, kelas itu tidak ada di CSS produksi (Tailwind memindai file).

## 13. VM uji (E2E)

- **JOINER2** (VM Windows 10 untuk uji): `E:\New folder (37)\Clone of JOINER2.vmx`
  · VNC `127.0.0.1:5901` (password di `LOCAL-SECRETS.md`) · `vmrun.exe` di `E:\New folder (31)\vmrun.exe`
  (`vmrun -T ws start "<vmx>" gui | stop "<vmx>" hard | reset "<vmx>"`).
- Kontrol VNC: `python scripts\vnc-driver.py "<action>"` (env `PELOG_VNC_SERVER`, `PELOG_VNC_PASSWORD`),
  aksi: `key:`, `combo:`, `type:`, `click:X,Y`, `capture:file`, `sleep:S`. Resolusi 1718×928.
- Akun VM: `goldpump` (auto-login admin), instalasi diam-diam `/VERYSILENT /SUPPRESSMSGBOXES /NORESTART`
  (UAC: klik "Yes" di ~(726,600) ±4 detik setelah prompt).
- S serves file ke VM lewat HTTP server di host (port 8090 → folder `E:\pelog-build\tmp`).
- Skenario uji wajib: enroll → login → sesi → screenshot (manual & terjadwal) → metrik →
  laporan → **Matikan** → reboot → status "Tersedia" → idle-shutdown → admin close → shutdown →
  hapus perangkat → self-wipe → autostart setelah reboot → **khusus**: matikan di tengah enroll
  → boot ulang → **desktop harus normal** (regresi yang baru diperbaiki).

## 14. Database & waktu

- MySQL/MariaDB: `NOW()` = **WITA**, kolom timestamp disimpan **UTC** → di SQL server
  selalu pakai `UTC_TIMESTAMP()` untuk perbandingan waktu.
- Tabel penting: `users` (role: admin_utama/sub_admin/viewer, is_active), `students`, `staff_members`,
  `subjects`, `devices`, `usage_sessions` (close_reason: normal/recovery/shutdown/admin/idle),
  `screenshots` (+`content_hash`), `audit_logs`, `settings`, `sessions/cache/jobs` (Laravel).

## 15. Jebakan & pelajaran (WAJIB dibaca sebelum menyentuh kode/deploy)

1. **`git fetch` dari server ke GitHub = menggantung** → deploy file **tidak** lewat git; tulis file via ops-runner. (Repo lokal tetap di-push normal.)
2. **Migrasi enum**: urutan wajib **longgarkan → konversi data → sempitkan**. Salah urutan = error 1265 (MySQL) / CHECK constraint (SQLite) saat `migrate` produksi.
3. **Key `Policies` dilindungi ACL** → penulisan/penghapusan registry non-elevated bisa **ditolak**; selalu siapkan fallback elevated (task) + `try/catch`.
4. **`public/build` pernah milik root** → `www` tak bisa hapus/overwrite. Solusi: rename dalam parent yang milik `www`, lalu hapus sisa lewat panel (`?action=DeleteDir`).
5. **Panel**: API butuh **path sesi `apsess_...`** + `x-http-token` + cookie + **User-Agent browser**; entrance & sesi **berrotasi** → ambil ulang dari user bila 404.
6. **Selalu tutup runner ops** (404) + hapus `_up`/tmp; **jangan** restore file dari recycle bin aaPanel (pernah runner lama muncul kembali).
7. **Nginx** butuh override `fastcgi_intercept_errors off` (sudah terpasang — jangan dihapus).
8. **Auto-update** hanya memicu bila versi server **lebih besar** → republish versi sama tidak mendarat di perangkat lama.
9. **Screenshot ganda** bukan bug server: kiosk capture 2× dalam ~1 detik (manual bertemu interval) → diperbaiki client (jarak 90 detik + pending-aware) + server (dedupe 5 menit).
10. **`fake()->image()` di Laravel deterministik** (gambar sama) → tes screenshot bisa lolos palsu; pakai helper gambar berwarna berbeda.
11. **Jam laptop bisa meleset** (KOM_EDITING_1 +1 jam) → bandingkan dengan `server_time` API, bukan jam lokal.
12. **Durasi 60+ menit** tampil "1 jam" (bukan "60 menit") — sudah handled `Duration::human()`.
13. **Kode enrollment hanya tersimpan sebagai hash** → plaintext lama tidak bisa dibaca; kalau hilang, buat kode baru (dashboard/command) **lalu build ulang installer berkode** (±2 menit).
14. **Kontrol Panel tidak diblokir saat siswa login** (by design: `EnterSessionMode` me-suspend hardening; masuk lagi = desktop normal). Kalau perlu mengunci, pakai mode admin.
15. **Task Manager/CMD memang diblokir** saat layar kunci — namun Watchdog hidupkan lagi ≤2 menit kalau proses dibunuh.
16. **Deprecation `PDO::MYSQL_ATTR_SSL_CA`** muncul di `php artisan test` (PHP 8.5) — noise lingkungan, bukan bug; `vendor\bin\phpunit` lebih bersih.
17. **Vue/Tailwind**: kelas baru harus `npm run build` + upload `public/build` (tidak ikut git).
18. **Unggahan besar** selalu chunk 8 MB + join (limit panel/nginx).

## 16. Status produksi (snapshot 15 Okt 2026)

- Rilis server: **1.0.3**; installer produksi (berkode `BLG-XMQS-TB3C`) sha `a3d946f2…`; publik sha `7e6f37d9…`;
  `PELOG-Revert.exe` sha `a50924c4…` (di server & dashboard).
- Data (per 07 Okt 2026): 1.242 siswa · 105 guru/pegawai · 19 mapel · 3 perangkat · 4 sesi · 2 screenshot.
- Enrollment aktif: `BLG-XMQS-TB3C` (tampil di Pengaturan + tombol Salin).
- Situs lain di server: **jangan disentuh**.

## 17. TODO / hal yang masih menggantung

1. **Revoke token GitHub** (`ghp_…`) setelah selesai dipakai — sudah beberapa kali terekspos di chat.
2. **Kosongkan recycle bin aaPanel** (pernah runner lama kembali aktif).
3. **Rilis 1.0.3 sudah dipublikasikan** — pantau perangkat yang masih 1.0.2 di dashboard; updater lama akan memasang 1.0.3 saat boot.
4. **Verifikasi multi-monitor nyata** (panel penutup + screenshot semua layar) di laptop 2-layar asli — belum bisa diuji di VM (host 1 layar).
5. Ganti password panel & putar entrance setelah paparan ini.
6. `docs/VERIFICATION.md` perlu diperbarui dengan hasil uji 15–21 Okt 2026.

## 18. Konvensi kerja

- S Communicate ke user: **Bahasa Indonesia**, ringkas, sertakan path file & bukti (SHA, HTTP code, screenshot path).
--before Claim: jalankan tes/verifikasi, jangan mengklaim tanpa bukti.
- Perubahan besar → breaking/plan dulu; perubahan kecil → langsung + tes.
- Server = produksi nyata: deploy setelah verifikasi lokal hijau.
- Hapus file sementara (runner, chunk, cookie jar) setelah selesai.

## 19. Glosarium

| Istilah | Arti |
|---|---|
| Kiosk | Aplikasi layar kunci di laptop siswa |
| Enroll | Tukar kode enrollment menjadi device token |
| Sesi | Satu periode pemakaian laptop (mapel/tujuan/durasi) |
| Cover | Panel penutup monitor kedua saat layar kunci |
| Mode admin | Mode perawatan (Ctrl+Alt+Shift+B) — suspend policy |
| Parole | Task pengaman yang melepas policy bila kiosk gagal tampil |
| Ops runner | PHP sementara di `public/` untuk menjalankan perintah shell di server |
| Hardening | Policy registry penguncian (DisableCMD, NoRun, DisallowRun, …) |
| Self-wipe | Kiosk menghapus dirinya saat perangkat dicabut dari dashboard |
| Staging update | Installer unduhan yang menunggu dipasang task SYSTEM |
