# Checklist Uji End-to-End di VM (Fase 6)

Mesin host : Windows 10 (opencode) — server staging & file installer
VM target  : `E:\New folder (33)\Windows 10 x64.vmx` (VMware Workstation 17.6.4)
Installer  : `E:\pelog-build\installer\PELOG_Setup.exe` (66 MB)
Unduhan VM : http://192.168.0.100:8090/PELOG_Setup.exe
API server : http://192.168.0.100:8000 (bind 0.0.0.0, LAN OK)
vmrun      : `E:\New folder (31)\vmrun.exe`

> **Snapshot VM SEBELUM menguji hardening.** Jika terkunci, revert snapshot adalah penyelamat tercepat.

---

## Skenario Uji

| # | Skenario | Langkah | Hasil yang diharapkan | Status |
|---|---|---|---|---|
| 1 | Install | Jalankan installer di VM (Run as admin) | Selesai tanpa error; kiosk terbuka | ⬜ |
| 2 | Enrollment | Masukkan kode dari dashboard | Terdaftar; perangkat muncul di dashboard | ⬜ |
| 3 | Login siswa (set PIN) | NISN baru → buat PIN 4 angka | PIN tersimpan; lanjut ke detail | ⬜ |
| 4 | Sesi berjalan | Pilih mapel + tujuan → MULAI | Widget stopwatch muncul; dashboard "Digunakan" | ⬜ |
| 5 | Screenshot menit ke-N | Tunggu timer (TestMode = menit 1) | Screenshot tampil di galeri dashboard | ⬜ |
| 6 | Selesai + feedback | Klik SELESAI → isi refleksi → simpan | Sesi tertutup; laporan memuat feedback | ⬜ |
| 7 | Login berikutnya | NISN sama → masukkan PIN yang dibuat | Berhasil masuk tanpa set ulang | ⬜ |
| 8 | PIN salah 5× | Ulangi PIN salah | Terkunci sementara (lockout) | ⬜ |
| 9 | Offline | Matikan adapter jaringan VM → login & sesi | Banner offline; sesi tetap tercatat | ⬜ |
| 10 | Sync tertunda | Nyalakan jaringan → tunggu 30 dtk | Sesi offline muncul di dashboard | ⬜ |
| 11 | Mati listrik | Reset VM saat sesi aktif → boot ulang | Sesi ditutup `recovery` (durasi wajar) | ⬜ |
| 12 | Hardening aktif | Log in sebagai akun siswa → cek Task Manager/CMD/Regedit | Semua terkunci; Alt+Tab & Win key tertahan | ⬜ |
| 13 | Akun admin aman | Log in akun admin → buka Task Manager | Normal (tidak terpengaruh) | ⬜ |
| 14 | Mode Admin | Ctrl+Alt+Shift+B → password → maintenance | Hardening ditangguhkan; banner tampil | ⬜ |
| 15 | Kembali ke kiosk | Klik "KEMBALI KE KIOSK" | Hardening aktif kembali | ⬜ |
| 16 | Autostart | Restart VM → login | Kiosk terbuka otomatis | ⬜ |
| 17 | Watchdog | Matikan proses kiosk (via akun admin) | Kiosk hidup kembali ≤ 2 menit | ⬜ |
| 18 | Uninstall | Settings > Apps → uninstall | Task & autostart terhapus; unlock-admin tersedia | ⬜ |

## Catatan Konfigurasi VM

Setelah install, edit `C:\ProgramData\PELOG\pelog.json` bila perlu:

```json
{
  "base_url": "http://192.168.0.100:8000",
  "test_mode": false,
  "hardening_enabled": true
}
```

- Untuk uji cepat screenshot: `test_mode: true` (timer screenshot 1 menit).
- Untuk uji awal tanpa risiko terkunci: `hardening_enabled: false` dahulu.

## Bukti

Screenshot tiap skenario disimpan di: `docs/evidence-fase-6/`
