# PELOG

**Buku Aktivitas Laptop & Informasi Device Log** â€” Sistem monitoring aktivitas komputer & laptop
SMK Negeri 1 Mas, Ubud (*Kriya Kencana Raksa*).

## Komponen

| Folder | Isi |
|---|---|
| `server/` | Laravel 12 â€” REST API v1 + dashboard web (Blade + Tailwind + Alpine) |
| `client/` | Aplikasi kiosk Windows (WinForms .NET 8) â€” *Fase 4* |
| `installer/` | Inno Setup + script hardening/tooling â€” *Fase 5* |
| `scripts/` | Script development (env, database, serve, build) |
| `docs/` | Dokumentasi proyek (arsitektur, API, DB, SOP, privasi, verifikasi) |
| `assets/` | Logo sekolah, font, ikon |

## Spesifikasi Ringkas

- **Login siswa:** NISN + tanggal lahir (tanpa PIN; verifikasi bisa offline)
- **Login guru:** NIP (tanpa PIN, tanpa feedback, tanpa screenshot)
- **Screenshot:** 1x per sesi di menit ke-30 (WebP q70, fallback JPEG q60, maks 1280 px)
- **Offline:** cache siswa/guru/mapel + antrean sinkronisasi otomatis
- **Dashboard:** admin IT (penuh) + guru (read-only)
- **Storage:** retensi permanen + alert disk + backup

## Development

```powershell
# 1) Aktifkan environment (semua cache & output berat diarahkan ke drive E:)
. .\scripts\env.ps1

# 2) Nyalakan MariaDB staging (XAMPP, port 3307)
.\scripts\dev-db.ps1

# 3) Jalankan server staging
.\scripts\dev-serve.ps1
```

Akses dashboard: <http://127.0.0.1:8000>

## Database Staging

- MariaDB (XAMPP) â€” `127.0.0.1:3307`, database `pelog`
- Kredensial ada di `server/.env` (user khusus `pelog`, bukan root)

## Produksi

- URL: <https://pelog.smkn1mas.sch.id> (aaPanel: Nginx + PHP 8.4, MySQL)
- Akun awal (dibuat seeder): `admin@pelog.local` / `Pelog!Admin2026` dan
  `guru@pelog.local` / `Pelog!Guru2026` â€” **ganti setelah instalasi**. Seeder
  produksi hanya membuat akun awal + pengaturan; tanpa data contoh.
- Base URL client: `https://pelog.smkn1mas.sch.id` (installer menulisnya ke
  `C:\ProgramData\PELOG\pelog.json`).
- Panduan deploy/operasional lengkap: [`docs/DEPLOY-PRODUCTION.md`](docs/DEPLOY-PRODUCTION.md).

## Status Fase

- [x] **Fase 0** â€” Environment, tooling, scaffold repo & Laravel
- [x] **Fase 1** â€” Fondasi backend: migrasi, model, auth, design token
- [x] **Fase 2** â€” REST API v1 + test
- [x] **Fase 3** â€” Dashboard web + gate desain
- [x] **Fase 4** â€” Client kiosk (.NET 8) + 27 test (termasuk E2E server nyata)
- [x] **Fase 5** â€” Hardening + installer (`E:\pelog-build\installer\PELOG_Setup.exe`)
- [x] **Fase 6** â€” E2E di VMware (20 skenario, lihat `docs/VERIFICATION.md`)
- [ ] Fase 7 â€” Verifikasi menyeluruh + dokumentasi akhir *(berjalan â€” laporan ada di `docs/VERIFICATION.md`)*

> **Laporan verifikasi lengkap:** `docs/VERIFICATION.md`
> **Bukti uji VM:** `docs/evidence-fase-6/` Â· **Pratinjau desain:** `docs/design-preview-v2/`
