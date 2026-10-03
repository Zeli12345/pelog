# PELOG — Panduan Deploy Produksi (aaPanel)

Server produksi: **https://pelog.smkn1mas.sch.id** (aaPanel).

> **Penting:** hanya kelola situs PELOG. Jangan mengubah situs/DB lain di server.

## 1. Prasyarat server

- aaPanel dengan Nginx + PHP **8.3+/8.4** (produksi saat ini PHP 8.4.14).
- Ekstensi PHP wajib: `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `curl`, `dom`,
  `fileinfo`, `filter`, `hash`, `session`, `tokenizer`, `xml`, `zip`, `intl`, `gd`.
- MySQL 5.7+/MariaDB 10.3+.
- Composer 2.x (`composer --version`).
- Git (deploy dari GitHub `Zeli12345/pelog`).

Struktur direktori di server:

```
/www/wwwroot/pelog.smkn1mas.sch.id/         <- root repo (git)
└── server/                                 <- aplikasi Laravel
    └── public/                             <- webroot (document root vhost)
```

## 2. Konfigurasi `.env` produksi

```ini
APP_NAME="PELOG"
APP_ENV=production
APP_KEY=base64:...            # php artisan key:generate --show
APP_DEBUG=false               # WAJIB false
APP_URL=https://pelog.smkn1mas.sch.id

APP_LOCALE=id
APP_TIMEZONE=UTC              # tampilan dashboard sudah dikonversi ke WITA

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<db_situs>
DB_USERNAME=<user_db>
DB_PASSWORD=<password_db>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database     # tidak ada job antrean; worker tidak diperlukan

LOG_CHANNEL=stack
LOG_LEVEL=warning
```

Vhost Nginx (otomatis dari aaPanel):

- `root /www/wwwroot/pelog.smkn1mas.sch.id/server/public;`
- Redirect HTTP → HTTPS aktif + HSTS.
- Sertifikat Let's Encrypt (`ssl_certificate .../fullchain.pem`).

## 3. Langkah deploy / update

```bash
cd /www/wwwroot/pelog.smkn1mas.sch.id

# 1) Kode terbaru
git fetch origin && git reset --hard origin/main
#    (atau git pull bila tidak ada perubahan lokal)

# 2) Dependensi produksi
cd server
composer install --no-dev --optimize-autoloader

# 3) Migrasi database
php artisan migrate --force

# 4) Bersihkan & bangun cache
php artisan optimize:clear
php artisan optimize

# 5) (Sekali saja) akun awal admin + guru
php artisan db:seed --force

# 6) Izin folder
chmod -R ug+rwx storage bootstrap/cache
```

> Setiap mengubah `.env`, jalankan ulang `php artisan optimize:clear && php artisan optimize`.

## 4. Akun awal (dibuat seeder)

| Peran | Email | Password awal |
|---|---|---|
| Admin IT | `admin@pelog.local` | `Pelog!Admin2026` |
| Guru | `guru@pelog.local` | `Pelog!Guru2026` |

- **Wajib** ganti password setelah instalasi (dashboard → Profil), atau set
  `PELOG_ADMIN_PASSWORD` / `PELOG_GURU_PASSWORD` di `.env` lalu jalankan ulang
  `php artisan db:seed --force` (password akun lama TIDAK ditimpa tanpa variabel ini).
- Seeder produksi **hanya** membuat akun di atas + pengaturan default.
  Data siswa/guru diimpor lewat dashboard; mapel dikelola dari menu Mapel.
  Data contoh hanya ada saat `APP_ENV=local`.

## 5. Penjadwal (wajib)

Sesi menggantung ditutup oleh `pelog:close-stale-sessions` tiap 5 menit.
Tambahkan satu Cron di aaPanel (menu **Cron**, user `root`, tiap 1 menit):

```bash
runuser -u www -- bash -lc 'cd /www/wwwroot/pelog.smkn1mas.sch.id/server && php artisan schedule:run >> storage/logs/scheduler.log 2>&1'
```

## 6. Rilis installer client (auto-update)

1. Build installer di mesin dev (base_url produksi):

   ```powershell
   .\scripts\build-client.ps1
   .\scripts\build-installer.ps1 -BaseUrl "https://pelog.smkn1mas.sch.id" -EnrollmentCode "<kode-enrollment>"
   ```

2. Publikasikan ke server (di server):

   ```bash
   php artisan pelog:release-app /path/PELOG_Setup.exe <versi> --notes="catatan rilis"
   cp storage/app/private/releases/PELOG_Setup_<versi>.exe storage/app/private/client/PELOG_Setup.exe
   php artisan tinker --execute="\App\Models\Setting::setValue('client_latest_version','<versi>');"
   ```

   Atau lewat dashboard: **Pengaturan → Unggah installer client** (maks 200 MB).

3. Kiosk memeriksa pembaruan tiap 6 jam (dapat diubah di Pengaturan). Unduhan
   ada di `/downloads/client-setup` (hanya bila rilis tersedia).

## 7. Kode enrollment

```bash
php artisan pelog:enrollment-code   # tampil sekali; salin ke installer
```

Kode disimpan sebagai hash (tidak bisa dilihat lagi). Kode juga bisa dibuat
dari dashboard: **Pengaturan → Buat kode enrollment baru**.

## 8. Keamanan & operasional

- `APP_DEBUG=false` di produksi; cek `https://pelog.smkn1mas.sch.id/login` tidak
  menampilkan stack trace saat error.
- Panel aaPanel: batasi IP (whitelist) dan jangan pakai path/password default.
- **Jangan** biarkan MySQL (3306) terbuka ke internet — batasi firewall ke
  localhost/kantor.
- Kredensial enrollment = kunci pendaftaran perangkat; jangan disebar publik.
- Backup rutin: database + `server/storage/app/private` (screenshot & installer).
- Log: `server/storage/logs/laravel.log`.

## 9. Rollback cepat

```bash
cd /www/wwwroot/pelog.smkn1mas.sch.id
git reset --hard <commit-sebelumnya>
cd server && composer install --no-dev --optimize-autoloader && php artisan optimize
```

## 10. Uji pasca-deploy

```bash
curl -s https://pelog.smkn1mas.sch.id/api/v1/health      # {"status":"healthy"}
curl -s https://pelog.smkn1mas.sch.id/api/v1/version     # app_version & latest_version
```

- Login dashboard dengan akun admin.
- Pastikan `APP_DEBUG=false` (buka URL tidak dikenal → halaman 404 rapi).
- Jalankan cron, cek `storage/logs/scheduler.log`.
