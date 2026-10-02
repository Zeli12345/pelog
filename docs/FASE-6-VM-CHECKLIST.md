# Checklist Uji End-to-End di VM (Fase 6)

Mesin host : Windows 10 (opencode) â€” server staging & file installer
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
| 1 | Install | Jalankan installer di VM (Run as admin) | Selesai tanpa error; kiosk terbuka | â¬œ |
| 2 | Enrollment | Masukkan kode dari dashboard | Terdaftar; perangkat muncul di dashboard | â¬œ |
| 3 | Login siswa (set PIN) | NISN baru â†’ buat PIN 4 angka | PIN tersimpan; lanjut ke detail | â¬œ |
| 4 | Sesi berjalan | Pilih mapel + tujuan â†’ MULAI | Widget stopwatch muncul; dashboard "Digunakan" | â¬œ |
| 5 | Screenshot menit ke-N | Tunggu timer (TestMode = menit 1) | Screenshot tampil di galeri dashboard | â¬œ |
| 6 | Selesai + feedback | Klik SELESAI â†’ isi refleksi â†’ simpan | Sesi tertutup; laporan memuat feedback | â¬œ |
| 7 | Login berikutnya | NISN sama â†’ masukkan PIN yang dibuat | Berhasil masuk tanpa set ulang | â¬œ |
| 8 | PIN salah 5Ã— | Ulangi PIN salah | Terkunci sementara (lockout) | â¬œ |
| 9 | Offline | Matikan adapter jaringan VM â†’ login & sesi | Banner offline; sesi tetap tercatat | â¬œ |
| 10 | Sync tertunda | Nyalakan jaringan â†’ tunggu 30 dtk | Sesi offline muncul di dashboard | â¬œ |
| 11 | Mati listrik | Reset VM saat sesi aktif â†’ boot ulang | Sesi ditutup `recovery` (durasi wajar) | â¬œ |
| 12 | Hardening aktif | Log in sebagai akun siswa â†’ cek Task Manager/CMD/Regedit | Semua terkunci; Alt+Tab & Win key tertahan | â¬œ |
| 13 | Akun admin aman | Log in akun admin â†’ buka Task Manager | Normal (tidak terpengaruh) | â¬œ |
| 14 | Mode Admin | Ctrl+Alt+Shift+B â†’ password â†’ maintenance | Hardening ditangguhkan; banner tampil | â¬œ |
| 15 | Kembali ke kiosk | Klik "KEMBALI KE KIOSK" | Hardening aktif kembali | â¬œ |
| 16 | Autostart | Restart VM â†’ login | Kiosk terbuka otomatis | â¬œ |
| 17 | Watchdog | Matikan proses kiosk (via akun admin) | Kiosk hidup kembali â‰¤ 2 menit | â¬œ |
| 18 | Uninstall | Settings > Apps â†’ uninstall | Task & autostart terhapus; unlock-admin tersedia | â¬œ |

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
