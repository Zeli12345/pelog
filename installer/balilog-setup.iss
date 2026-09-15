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
Name: "hardening"; Description: "Terapkan penguncian kiosk (Task Manager, CMD, Regedit, Win+R)"; GroupDescription: "Pengamanan:"; Flags: checkedonce
Name: "watchdog"; Description: "Pasang pengawas otomatis (menjalankan ulang kiosk bila tertutup)"; GroupDescription: "Pengamanan:"; Flags: checkedonce

[Files]
; 1) Aplikasi hasil publish
Source: "E:\balilog-build\publish\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

; 2) Konfigurasi client (dibuat oleh scripts\build-installer.ps1)
Source: "balilog.client.json"; DestDir: "{commonappdata}\BALI-LOG"; DestName: "balilog.json"; Flags: onlyifdoesntexist

; 3) Kode enrollment (opsional)
Source: "enrollment.txt"; DestDir: "{commonappdata}\BALI-LOG"; Flags: onlyifdoesntexist skipifsourcedoesntexist

; 4) Alat pemulihan & hardening untuk Admin IT
Source: "unlock-admin.ps1"; DestDir: "{app}"; Flags: ignoreversion
Source: "hardening.ps1"; DestDir: "{app}"; Flags: ignoreversion
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
; Terapkan penguncian kiosk untuk akun yang sedang login (installer berjalan sebagai admin)
Filename: "powershell.exe"; Parameters: "-ExecutionPolicy Bypass -File ""{app}\hardening.ps1"" -Apply"; Flags: runhidden; Tasks: hardening; StatusMsg: "Menerapkan penguncian kiosk..."
; Autostart saat logon (scheduled task, semua pengguna; berjalan sebagai pengguna yang login)
Filename: "schtasks.exe"; Parameters: "/Create /F /TN ""BALI-LOG Kiosk (Logon)"" /SC ONLOGON /TR ""{app}\BalilogKiosk.exe"""; Flags: runhidden; StatusMsg: "Mendaftarkan autostart kiosk..."
; Watchdog: cek tiap 2 menit, jalankan kiosk bila tidak berjalan
Filename: "schtasks.exe"; Parameters: "/Create /F /TN ""BALI-LOG Kiosk (Watchdog)"" /SC MINUTE /MO 2 /TR ""{app}\BalilogKiosk.exe"""; Flags: runhidden; Tasks: watchdog
; Jalankan aplikasi setelah instalasi
Filename: "{app}\BalilogKiosk.exe"; Description: "Jalankan BALI-LOG sekarang"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""BALI-LOG Kiosk (Logon)"""; Flags: runhidden; RunOnceId: "DelTaskLogon"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""BALI-LOG Kiosk (Watchdog)"""; Flags: runhidden; RunOnceId: "DelTaskWatchdog"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""BALILogHardeningSuspend"""; Flags: runhidden; RunOnceId: "DelTaskSuspend"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""BALILogHardeningApply"""; Flags: runhidden; RunOnceId: "DelTaskApply"

[UninstallDelete]
; Catatan: data (SQLite, screenshot tertunda) & konfigurasi di ProgramData sengaja TIDAK dihapus
; agar riwayat tidak hilang saat uninstall. Hapus manual bila diperlukan.

[Code]
// Tugas elevated untuk MODE ADMIN (suspend/apply kebijakan) TANPA prompt UAC.
// Dibuat lewat XML karena schtasks /TR tidak bisa menerima path ber-spasi
// dengan kutip di dalamnya ("Invalid argument/option").
const
  SuspendTaskName = 'BALILogHardeningSuspend';
  ApplyTaskName = 'BALILogHardeningApply';

function BuildTaskXml(const Args: string): string;
begin
  Result :=
    '<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">' + #13#10 +
    '  <RegistrationInfo>' + #13#10 +
    '    <Description>BALI-LOG kiosk hardening control</Description>' + #13#10 +
    '  </RegistrationInfo>' + #13#10 +
    '  <Triggers />' + #13#10 +
    '  <Principals>' + #13#10 +
    '    <Principal id="Author">' + #13#10 +
    '      <LogonType>InteractiveToken</LogonType>' + #13#10 +
    '      <RunLevel>HighestAvailable</RunLevel>' + #13#10 +
    '    </Principal>' + #13#10 +
    '  </Principals>' + #13#10 +
    '  <Settings>' + #13#10 +
    '    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>' + #13#10 +
    '    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>' + #13#10 +
    '    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>' + #13#10 +
    '    <AllowHardTerminate>true</AllowHardTerminate>' + #13#10 +
    '    <StartWhenAvailable>true</StartWhenAvailable>' + #13#10 +
    '    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>' + #13#10 +
    '    <AllowStartOnDemand>true</AllowStartOnDemand>' + #13#10 +
    '    <Enabled>true</Enabled>' + #13#10 +
    '    <Hidden>false</Hidden>' + #13#10 +
    '    <RunOnlyIfIdle>false</RunOnlyIfIdle>' + #13#10 +
    '    <WakeToRun>false</WakeToRun>' + #13#10 +
    '    <ExecutionTimeLimit>PT1H</ExecutionTimeLimit>' + #13#10 +
    '    <Priority>7</Priority>' + #13#10 +
    '  </Settings>' + #13#10 +
    '  <Actions Context="Author">' + #13#10 +
    '    <Exec>' + #13#10 +
    '      <Command>powershell.exe</Command>' + #13#10 +
    '      <Arguments>' + Args + '</Arguments>' + #13#10 +
    '    </Exec>' + #13#10 +
    '  </Actions>' + #13#10 +
    '</Task>';
end;

procedure CreateHardeningTask(const TaskName, Mode: string);
var
  XmlPath: string;
  Args: string;
  ResultCode: Integer;
begin
  Args := '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File &quot;' +
          ExpandConstant('{app}') + '\hardening.ps1&quot; ' + Mode;

  XmlPath := ExpandConstant('{tmp}\' + TaskName + '.xml');
  SaveStringToFile(XmlPath, BuildTaskXml(Args), False);

  if not Exec('schtasks.exe',
      '/Create /F /TN "' + TaskName + '" /XML "' + XmlPath + '"',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
  begin
    Log('BALI-LOG: gagal menjalankan schtasks untuk ' + TaskName);
  end
  else if ResultCode <> 0 then
  begin
    Log('BALI-LOG: schtasks ' + TaskName + ' keluar dengan kode ' + IntToStr(ResultCode));
  end;

  DeleteFile(XmlPath);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if (CurStep = ssPostInstall) and WizardIsTaskSelected('hardening') then
  begin
    CreateHardeningTask(SuspendTaskName, '-Suspend');
    CreateHardeningTask(ApplyTaskName, '-Apply');
  end;
end;
