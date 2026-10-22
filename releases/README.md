# Rilis PELOG

Installer siap pakai untuk mesin baru.

## PELOG 1.0.3 (22 Okt 2026)

- Berkas   : `PELOG_Setup_1.0.3.exe` (55.4 MB, Inno Setup, **tanpa kode enrollment**)
- SHA-256  : `737011b0885772f32b5486c4204265a5a4f1df1061d02f5ce76930a5ec57851c`
- Build produksi (berkode `BLG-XMQS-TB3C`, untuk auto-update & dashboard):
  SHA-256 `ff1ee22329d5279fc1604384ef63558a1a634002a0805dd2f508fdee816f708c`
- Fitur/Perbaikan:
  - **Galeri Media** (pengganti halaman Screenshot): lihat layar, webcam, atau
    keduanya; filter jenis/perangkat/tanggal (WITA)/pencarian perangkat; lightbox.
  - **Lokasi/ruangan**: dropdown di detail perangkat & enrollment client,
    tambah ruangan dari client (tanpa hapus), pencegahan duplikat, merge/arsip,
    dan penghapusan berlapis pengaman.
  - **Denah ruangan**: kartu ruangan → editor drag-and-drop, meja kotak/bulat,
    susun otomatis, optimistic locking.
  - **Catatan perangkat**: tab riwayat sesi/catatan/aktivitas, kategori, status,
    prioritas, biaya, vendor, tag, lampiran, pinned.
  - **Inventaris generik**: kategori bebas, atribut JSON, stok minimum, mutasi
    masuk/keluar/adjust/transfer/repair/return/write-off, posisi rak/laci.
  - **Webcam default aktif** untuk guru & siswa: capture langsung saat kamera
    dipakai, berikutnya sesuai interval (default 15 menit), antrean offline.
  - **Auto-update v2**: agen SYSTEM mengunduh & memverifikasi SHA-256 sendiri,
    state `updates/state.json`, layar pembaruan yang memblokir login sebelum
    versi terpasang, ACL folder updates, verifikasi versi setelah install,
    retry maksimal 3, dan server menolak sesi di bawah versi minimum.
- Perangkat 1.0.2 akan mengunduh 1.0.3 lewat updater lama, lalu memasangnya saat
  boot berikutnya. Setelah 1.0.3 terpasang, gate baru aktif untuk rilis berikutnya.

## PELOG 1.0.2 (build 2, replublished 15 Okt 2026)

- Berkas   : `PELOG_Setup_1.0.2.exe` (51.9 MB, Inno Setup)
- SHA-256  : `1ffb948e463afa8a05c44d8d34bab8c7e4035fe9a95799526226a601cf7f3eb4`
- Build produksi (berkode kode enrollment — untuk auto-update & unduhan dashboard):
  SHA-256 `88d65ceb2a3cc59b89b6d28ad8ff6d9c18835d39705c854911afab417501a809` (51.9 MB)
- Perbaikan **FATAL**: laptop bisa jadi "rusak" (CMD/PowerShell/Control Panel/
  Task Manager terkunci) bila PC dimatikan di **tahap pendaftaran (enroll)** lalu
  di-boot ulang. Rantai: installer menerapkan policy HKCU saat instalasi → tahap
  enroll tidak membersihkan/menegakkan apa pun → bila kiosk gagal tampil setelah
  login, desktop terkunci tanpa jalan keluar. Sekarang:
  1. **Policy tidak lagi dipasang saat instalasi** — hanya setelah perangkat
     terdaftar (layar kunci).
  2. Kiosk **membersihkan policy pada tahap enroll**, saat keluar, dan **saat
     startup gagal** (langsung HKCU + fallback task elevated
     `PelogHardeningSuspend` bila key dilindungi ACL).
  3. **Task baru "PelogParole"** (jaring pengaman): bila kiosk tidak muncul
     dalam 10 menit setelah login, policy dilepaskan otomatis
     (log: `C:\Users\Public\pelog-parole.log`).
- Perbaikan lain 1.0.2 (build 1): **anti-dobel screenshot** (jarak minimum 90
  detik, pending-aware) + server menggabungkan unggahan identik dalam 5 menit
  (`screenshot_deduplicated`), pagination halaman Screenshot (10/25/50/100), detik
  pada waktu capture, format durasi "N jam M menit".
- Fitur baru (klien): lintasan startup & pendaftaran dibersihkan — hardening
  kini hanya berlaku setelah perangkat terdaftar; tidak ada lagi jendela
  terkunci, dengan log publik `C:\Users\Public\pelog-revert.log` di sisi alat.
- **PELOG-Revert.exe** (alat perbaikan laptop terkunci): `tools/PELOG-Revert.exe`
  — jalankan dengan akun admin: menghentikan kiosk, melepaskan semua kebijakan
  registry (semua akun yang ter-muat), menghapus 7 scheduled task + autostart,
  memuat ulang Explorer; opsi "total" juga uninstaller + hapus data.
  SHA-256 `a50924c4f430e0e5f919f7a75ee04dbd2442a6a7a6d406e923f747abc033e9e4`.
- Catatan versi: **tetap 1.0.2** karena hanya perangkat 1.0.1 yang auto-update;
  varian ini adalah build untuk **instalasi baru/ulang** (hash berbeda dari build
  pertama karena kode berubah — perangkat yang sudah terpasang 1.0.2 tidak
  mengunduh ulang otomatis untuk versi yang sama).
- Varian di repo/Release ini **tanpa kode enrollment** (diisi manual saat instalasi).
  Build server/auto-update memakai varian berkode untuk pengisian otomatis.

## PELOG 1.0.1 (arsip)

- Berkas   : `PELOG_Setup_1.0.1.exe` (51.9 MB, Inno Setup)
- SHA-256  : `c9b12ae95c5106fd8fa3477ea25af6e1dfafcf95936dce4895fda118a31ef24d`
- Fitur    : penutup **semua monitor** saat layar kunci, screenshot berkala
  (interval dari Pengaturan) + minta screenshot manual, durasi live di laporan,
  status **Tersedia** setelah shutdown, auto-shutdown idle, tombol Matikan,
  metrik CPU/RAM/GPU + nama CPU, shutdown otomatis saat sesi ditutup admin.
- Perangkat yang masih memakai **1.0.0 akan memperbarui diri otomatis** ke 1.0.1
  (server mengiklankan rilis ini sebagai pembaruan).
- Varian di repo/Release ini **tanpa kode enrollment** (diisi manual saat instalasi).
  Build server/auto-update memakai varian berkode untuk pengisian otomatis.

## PELOG 1.0.0 (arsip)

- Berkas   : `PELOG_Setup_1.0.0.exe`
