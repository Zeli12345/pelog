# BALI-LOG

**Buku Aktivitas Laptop & Informasi Device Log** — Sistem monitoring aktivitas komputer & laptop
SMK Negeri 1 Mas, Ubud (*Kriya Kencana Raksa*).

## Komponen

| Folder | Isi |
|---|---|
| `server/` | Laravel 12 — REST API v1 + dashboard web (Blade + Tailwind + Alpine) |
| `client/` | Aplikasi kiosk Windows (WinForms .NET 8) — *Fase 4* |
| `installer/` | Inno Setup + script hardening/tooling — *Fase 5* |
| `scripts/` | Script development (env, database, serve, build) |
| `docs/` | Dokumentasi proyek (arsitektur, API, DB, SOP, privasi, verifikasi) |
| `assets/` | Logo sekolah, font, ikon |

## Spesifikasi Ringkas

- **Login siswa:** NISN + PIN 4 digit (buat PIN hanya saat online; verifikasi PIN bisa offline)
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

- MariaDB (XAMPP) — `127.0.0.1:3307`, database `balilog`
- Kredensial ada di `server/.env` (user khusus `balilog`, bukan root)

## Status Fase

- [x] **Fase 0** — Environment, tooling, scaffold repo & Laravel
- [x] **Fase 1** — Fondasi backend: migrasi, model, auth, design token
- [x] **Fase 2** — REST API v1 + test
- [x] **Fase 3** — Dashboard web + gate desain
- [ ] Fase 4 — Client kiosk (.NET 8)
- [ ] Fase 5 — Hardening + installer
- [ ] Fase 6 — E2E di VMware
- [ ] Fase 7 — Verifikasi menyeluruh + dokumentasi akhir
