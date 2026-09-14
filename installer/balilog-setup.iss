; BALI-LOG Kiosk - Inno Setup Script
; Membungkus aplikasi client (hasil dotnet publish) + konfigurasi,
; mendaftarkan autostart HKLM, dan membuat Scheduled Task watchdog.

[Setup]
AppId={{8F1A2C64-3B7E-4F3A-9C21-BALI0G000001}
AppName=BALI-LOG Kiosk
AppVersion=1.0.0
AppPublisher=SMK Negeri 1 Mas Ubud
AppPublisherURL=https://balilog.smkn1mas.sch.id
DefaultDirName={autopf}\BALI-LOG Kiosk
DefaultGroupName=BALI-LOG
OutputDir=E:\balilog-build\installer
OutputBaseFilename=BALI-LOG_Setup
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
DisableProgramGroupPage=yes
UninstallDisplayName=BALI-LOG Kiosk

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Buat ikon Desktop"; GroupDescription: "Ikon:"; Flags: unchecked
Name: "watchdog"; Description: "Pasang pengawas otomatis (menjalankan ulang kiosk bila tertutup)"; GroupDescription: "Pengamanan:"; Flags: checkedonce

[Files]
; 1) Aplikasi hasil publish
Source: "E:\balilog-build\publish\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

; 2) Konfigurasi client (dibuat oleh scripts\build-installer.ps1)
Source: "balilog.client.json"; DestDir: "{commonappdata}\BALI-LOG"; Flags: onlyifdoesntexist

; 3) Kode enrollment (opsional)
Source: "enrollment.txt"; DestDir: "{commonappdata}\BALI-LOG"; Flags: onlyifdoesntexist skipifsourcedoesntexist

; 4) Alat pemulihan untuk Admin IT
Source: "unlock-admin.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "README-OPS.txt"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{autoprograms}\BALI-LOG Kiosk"; Filename: "{app}\BalilogKiosk.exe"
Name: "{autodesktop}\BALI-LOG Kiosk"; Filename: "{app}\BalilogKiosk.exe"; Tasks: desktopicon

[Registry]
; Autostart untuk semua pengguna Windows (kiosk langsung tampil setelah login)
Root: HKLM; Subkey: "SOFTWARE\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "BALILogKiosk"; ValueData: """{app}\BalilogKiosk.exe"""; Flags: uninsdeletevalue

[Run]
; Izin akses data untuk semua akun (kiosk berjalan sebagai akun siswa, installer sebagai Admin)
Filename: "icacls.exe"; Parameters: """{commonappdata}\BALI-LOG"" /grant *S-1-5-32-545:(OI)(CI)M /T /C"; Flags: runhidden; StatusMsg: "Menyiapkan izin folder data..."
; Autostart saat logon (scheduled task, semua pengguna; berjalan sebagai pengguna yang login)
Filename: "schtasks.exe"; Parameters: "/Create /F /TN ""BALI-LOG Kiosk (Logon)"" /SC ONLOGON /TR ""{app}\BalilogKiosk.exe"""; Flags: runhidden; StatusMsg: "Mendaftarkan autostart kiosk..."
; Watchdog: cek tiap 2 menit, jalankan kiosk bila tidak berjalan
Filename: "schtasks.exe"; Parameters: "/Create /F /TN ""BALI-LOG Kiosk (Watchdog)"" /SC MINUTE /MO 2 /TR ""{app}\BalilogKiosk.exe"""; Flags: runhidden; Tasks: watchdog
; Jalankan aplikasi setelah instalasi
Filename: "{app}\BalilogKiosk.exe"; Description: "Jalankan BALI-LOG sekarang"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""BALI-LOG Kiosk (Logon)"""; Flags: runhidden; RunOnceId: "DelTaskLogon"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""BALI-LOG Kiosk (Watchdog)"""; Flags: runhidden; RunOnceId: "DelTaskWatchdog"

[UninstallDelete]
; Catatan: data (SQLite, screenshot tertunda) & konfigurasi di ProgramData sengaja TIDAK dihapus
; agar riwayat tidak hilang saat uninstall. Hapus manual bila diperlukan.
