' PELOG - reset kiosk dari akun siswa (workaround saat hardening aktif).
' Dipakai bila cmd.exe/.bat diblokir policy (DisableCMD=2) dan PowerShell diblokir
' DisallowRun: jalankan file ini lewat wscript (mis. unduh via browser lalu Run).
'
' Efek: hentikan kiosk, hapus database lokal (antrean + token), jalankan ulang
' aplikasi agar dialog enrollment tampil kembali. Data di server tidak diubah.

Set sh  = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
Set wmi = GetObject("winmgmts:\\.\root\cimv2")

' Hentikan kiosk
For Each p In wmi.ExecQuery("SELECT * FROM Win32_Process WHERE Name='PelogKiosk.exe'")
  p.Terminate()
Next

WScript.Sleep 2500

' Hapus database lokal (token & antrean lama) agar dapat enroll ulang
On Error Resume Next
fso.DeleteFile "C:\ProgramData\PELOG\data\local.db", True
fso.DeleteFile "C:\ProgramData\PELOG\data\local.db-wal", True
fso.DeleteFile "C:\ProgramData\PELOG\data\local.db-shm", True
On Error GoTo 0

' Jalankan ulang aplikasi
sh.Run """C:\Program Files\PELOG Kiosk\PelogKiosk.exe""", 1, False
