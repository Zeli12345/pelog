; PELOG Kiosk - Inno Setup Script
; Membungkus aplikasi client (hasil dotnet publish) + konfigurasi,
; mendaftarkan autostart HKLM, dan membuat Scheduled Task watchdog.

[Setup]
AppId={{8F1A2C64-3B7E-4F3A-9C21-BALI0G000001}
AppName=PELOG Kiosk
AppVersion=1.0.2
AppPublisher=SMK Negeri 1 Mas Ubud
AppPublisherURL=https://pelog.smkn1mas.sch.id
DefaultDirName={autopf}\PELOG Kiosk
DefaultGroupName=PELOG
OutputDir=E:\pelog-build\installer
OutputBaseFilename=PELOG_Setup
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
DisableProgramGroupPage=yes
UninstallDisplayName=PELOG Kiosk

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Buat ikon Desktop"; GroupDescription: "Ikon:"; Flags: unchecked
Name: "hardening"; Description: "Terapkan penguncian kiosk (Task Manager, CMD, Regedit, Win+R)"; GroupDescription: "Pengamanan:"; Flags: checkedonce
Name: "watchdog"; Description: "Pasang pengawas otomatis (menjalankan ulang kiosk bila tertutup)"; GroupDescription: "Pengamanan:"; Flags: checkedonce

[Files]
; 1) Aplikasi hasil publish (tanpa file debug *.pdb / dokumentasi *.xml.docs)
; restartreplace: saat pembaruan otomatis (SYSTEM) kiosk sedang berjalan,
; berkas yang terkunci dijadwalkan diganti Windows pada restart berikutnya
; alih-alih menggagalkan pemasangan (exit code 5).
Source: "E:\pelog-build\publish\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs restartreplace; Excludes: "*.pdb,*.xml.docs"

; 2) Konfigurasi client (dibuat oleh scripts\build-installer.ps1)
Source: "pelog.client.json"; DestDir: "{commonappdata}\PELOG"; DestName: "pelog.json"; Flags: onlyifdoesntexist

; 3) Kode enrollment (opsional)
Source: "enrollment.txt"; DestDir: "{commonappdata}\PELOG"; Flags: onlyifdoesntexist skipifsourcedoesntexist

; 4) Alat pemulihan & hardening untuk Admin IT
Source: "unlock-admin.ps1"; DestDir: "{app}"; Flags: ignoreversion restartreplace
Source: "hardening.ps1"; DestDir: "{app}"; Flags: ignoreversion restartreplace
Source: "apply-update.ps1"; DestDir: "{app}"; Flags: ignoreversion restartreplace
Source: "README-OPS.txt"; DestDir: "{app}"; Flags: ignoreversion restartreplace

[Icons]
Name: "{autoprograms}\PELOG Kiosk"; Filename: "{app}\PelogKiosk.exe"
Name: "{autodesktop}\PELOG Kiosk"; Filename: "{app}\PelogKiosk.exe"; Tasks: desktopicon

[Registry]
; Autostart untuk semua pengguna Windows (kiosk langsung tampil setelah login)
Root: HKLM; Subkey: "SOFTWARE\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "PelogKiosk"; ValueData: """{app}\PelogKiosk.exe"""; Flags: uninsdeletevalue

[Run]
; Izin akses data untuk semua akun (kiosk berjalan sebagai akun siswa, installer sebagai Admin)
Filename: "icacls.exe"; Parameters: """{commonappdata}\PELOG"" /grant *S-1-5-32-545:(OI)(CI)M /T /C"; Flags: runhidden; StatusMsg: "Menyiapkan izin folder data..."
; CATATAN: entri di bawah hanya untuk instalasi INTERAKTIF (mode senyap = pembaruan
; otomatis oleh SYSTEM; task sudah ada dan policy HKCU-SYSTEM tidak relevan).
; Terapkan penguncian kiosk untuk akun yang sedang login (installer berjalan sebagai admin)
Filename: "powershell.exe"; Parameters: "-ExecutionPolicy Bypass -File ""{app}\hardening.ps1"" -Apply"; Flags: runhidden; Tasks: hardening; Check: NotSilent; StatusMsg: "Menerapkan penguncian kiosk..."
; Autostart saat logon (scheduled task, semua pengguna; berjalan sebagai pengguna yang login)
Filename: "schtasks.exe"; Parameters: "/Create /F /TN ""PELOG Kiosk (Logon)"" /SC ONLOGON /TR ""{app}\PelogKiosk.exe"""; Flags: runhidden; Check: NotSilent; StatusMsg: "Mendaftarkan autostart kiosk..."
; Watchdog: cek tiap 2 menit, jalankan kiosk bila tidak berjalan
Filename: "schtasks.exe"; Parameters: "/Create /F /TN ""PELOG Kiosk (Watchdog)"" /SC MINUTE /MO 2 /TR ""{app}\PelogKiosk.exe"""; Flags: runhidden; Tasks: watchdog; Check: NotSilent
; Jalankan aplikasi setelah instalasi
Filename: "{app}\PelogKiosk.exe"; Description: "Jalankan PELOG sekarang"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""PELOG Kiosk (Logon)"""; Flags: runhidden; RunOnceId: "DelTaskLogon"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""PELOG Kiosk (Watchdog)"""; Flags: runhidden; RunOnceId: "DelTaskWatchdog"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""PelogHardeningSuspend"""; Flags: runhidden; RunOnceId: "DelTaskSuspend"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""PelogHardeningApply"""; Flags: runhidden; RunOnceId: "DelTaskApply"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""PelogAutoUpdate"""; Flags: runhidden; RunOnceId: "DelTaskAutoUpdate"
Filename: "schtasks.exe"; Parameters: "/Delete /F /TN ""PelogSelfWipe"""; Flags: runhidden; RunOnceId: "DelTaskSelfWipe"

[UninstallDelete]
; Catatan: data (SQLite, screenshot tertunda) & konfigurasi di ProgramData sengaja TIDAK dihapus
; agar riwayat tidak hilang saat uninstall. Hapus manual bila diperlukan.

[InstallDelete]
; Bersihkan sisa file sementara Inno Setup dari instalasi sebelumnya
; (mis. is-UW44ZX2FLC.tmp yang tertinggal). Pola dibatasi agar aman.
Type: files; Name: "{app}\is-*.tmp"
; Buang file debug (.pdb) dari instalasi lama - rilis 1.0.3+ tidak lagi
; menyertakan .pdb sehingga sisa lama (~130 MB) harus dibersihkan saat upgrade.
Type: files; Name: "{app}\*.pdb"

[Code]
// Tugas elevated untuk MODE ADMIN (suspend/apply kebijakan) TANPA prompt UAC.
// Dibuat lewat XML karena schtasks /TR tidak bisa menerima path ber-spasi
// dengan kutip di dalamnya ("Invalid argument/option").
const
  SuspendTaskName = 'PelogHardeningSuspend';
  ApplyTaskName = 'PelogHardeningApply';
  SelfWipeTaskName = 'PelogSelfWipe';

function NotSilent(): Boolean;
begin
  // Pembaruan otomatis berjalan senyap sebagai SYSTEM (/VERYSILENT): lewati
  // entri [Run] yang khusus instalasi interaktif (policy HKCU + task pengguna).
  Result := not WizardSilent;
end;

function BuildTaskXml(const Args, Description, TriggersXml, PrincipalsXml: string): string;
begin
  Result :=
    '<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">' + #13#10 +
    '  <RegistrationInfo>' + #13#10 +
    '    <Description>' + Description + '</Description>' + #13#10 +
    '  </RegistrationInfo>' + #13#10 +
    TriggersXml +
    PrincipalsXml +
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
  SaveStringToFile(XmlPath, BuildTaskXml(Args,
    'PELOG kiosk hardening control',
    '  <Triggers />' + #13#10,
    '  <Principals>' + #13#10 +
    '    <Principal id="Author">' + #13#10 +
    '      <LogonType>InteractiveToken</LogonType>' + #13#10 +
    '      <RunLevel>HighestAvailable</RunLevel>' + #13#10 +
    '    </Principal>' + #13#10 +
    '  </Principals>' + #13#10), False);

  if not Exec('schtasks.exe',
      '/Create /F /TN "' + TaskName + '" /XML "' + XmlPath + '"',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
  begin
    Log('PELOG: gagal menjalankan schtasks untuk ' + TaskName);
  end
  else if ResultCode <> 0 then
  begin
    Log('PELOG: schtasks ' + TaskName + ' keluar dengan kode ' + IntToStr(ResultCode));
  end;

  DeleteFile(XmlPath);
end;

// Task self-wipe: dipicu dashboard (perangkat dihapus) via
// "schtasks /run /tn PelogSelfWipe". Elevated tanpa prompt UAC
// (InteractiveToken + HighestAvailable), TANPA trigger (manual saja).
// hardening.ps1 -Wipe menghapus instalasi + data memakai uninstaller senyap.
procedure CreateSelfWipeTask();
var
  XmlPath: string;
  Args: string;
  ResultCode: Integer;
begin
  Args := '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File &quot;' +
          ExpandConstant('{app}') + '\hardening.ps1&quot; -Wipe';

  XmlPath := ExpandConstant('{tmp}\' + SelfWipeTaskName + '.xml');
  SaveStringToFile(XmlPath, BuildTaskXml(Args,
    'PELOG self wipe perangkat (dipicu dari dashboard)',
    '  <Triggers />' + #13#10,
    '  <Principals>' + #13#10 +
    '    <Principal id="Author">' + #13#10 +
    '      <LogonType>InteractiveToken</LogonType>' + #13#10 +
    '      <RunLevel>HighestAvailable</RunLevel>' + #13#10 +
    '    </Principal>' + #13#10 +
    '  </Principals>' + #13#10), False);

  if not Exec('schtasks.exe',
      '/Create /F /TN "' + SelfWipeTaskName + '" /XML "' + XmlPath + '"',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
  begin
    Log('PELOG: gagal menjalankan schtasks untuk ' + SelfWipeTaskName);
  end
  else if ResultCode <> 0 then
  begin
    Log('PELOG: schtasks ' + SelfWipeTaskName + ' keluar dengan kode ' + IntToStr(ResultCode));
  end;

  DeleteFile(XmlPath);
end;

// Task pembaruan otomatis: berjalan sebagai SYSTEM saat boot (+2 menit), tanpa
// prompt UAC. Kiosk hanya mengunduh & menaruh manifest; task inilah yang
// memasang installer secara senyap (ditunda bila kiosk/sesi sedang berjalan).
procedure CreateAutoUpdateTask();
var
  XmlPath: string;
  Args: string;
  ResultCode: Integer;
begin
  Args := '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File &quot;' +
          ExpandConstant('{app}') + '\apply-update.ps1&quot;';

  XmlPath := ExpandConstant('{tmp}\PelogAutoUpdate.xml');
  SaveStringToFile(XmlPath, BuildTaskXml(Args,
    'PELOG auto update - senyap saat boot',
    '  <Triggers>' + #13#10 +
    '    <BootTrigger>' + #13#10 +
    '      <Enabled>true</Enabled>' + #13#10 +
    '      <Delay>PT2M</Delay>' + #13#10 +
    '    </BootTrigger>' + #13#10 +
    '  </Triggers>' + #13#10,
    '  <Principals>' + #13#10 +
    '    <Principal id="Author">' + #13#10 +
    '      <UserId>S-1-5-18</UserId>' + #13#10 +
    '      <RunLevel>HighestAvailable</RunLevel>' + #13#10 +
    '    </Principal>' + #13#10 +
    '  </Principals>' + #13#10), False);

  if not Exec('schtasks.exe',
      '/Create /F /TN "PelogAutoUpdate" /XML "' + XmlPath + '"',
      '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
  begin
    Log('PELOG: gagal menjalankan schtasks untuk PelogAutoUpdate');
  end
  else if ResultCode <> 0 then
  begin
    Log('PELOG: schtasks PelogAutoUpdate keluar dengan kode ' + IntToStr(ResultCode));
  end;

  DeleteFile(XmlPath);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssPostInstall then
  begin
    // Selalu dibuat (interaktif maupun senyap) agar pembaruan berikutnya
    // tetap berjalan walau instalasi awal tidak mencentang opsi hardening.
    CreateAutoUpdateTask();
    // Task self-wipe juga selalu dibuat supaya penghapusan perangkat dari
    // dashboard dapat memicu wipe penuh tanpa prompt UAC.
    CreateSelfWipeTask();
  end;

  if (CurStep = ssPostInstall) and WizardIsTaskSelected('hardening') then
  begin
    CreateHardeningTask(SuspendTaskName, '-Suspend');
    CreateHardeningTask(ApplyTaskName, '-Apply');
  end;
end;
