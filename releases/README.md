# Rilis PELOG

Installer siap pakai untuk mesin baru.

## PELOG 1.0.2

- Berkas   : `PELOG_Setup_1.0.2.exe` (51.9 MB, Inno Setup)
- SHA-256  : `4ca5a868a80193d5ef837f6b3bda8f04af4e56c695c1cf8ced01227d4ed2f885`
- Perbaikan: **anti-dobel screenshot** — capture manual & terjadwal kini saling
  menghormati (jarak minimum 90 detik) dan mengirim capture tertunda alih-alih
  menangkap ulang; server juga menggabungkan unggahan dengan isi identik dalam
  jendela 5 menit (satu baris, tercatat di Audit sebagai `screenshot_deduplicated`).
- Fitur lain di sisi server: pagination halaman Screenshot kustom
  (10/25/50/100 per halaman) + waktu capture ditampilkan sampai detik.
- Perangkat **1.0.1 akan memperbarui diri otomatis** ke 1.0.2 (server mengiklankan
  rilis ini; cek setiap 6 jam / saat boot berikutnya).
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
