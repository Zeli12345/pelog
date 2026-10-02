# PELOG - Alat pemulihan Admin IT
# Menghapus kebijakan pengamanan kiosk PADA AKUN YANG SEDANG LOGIN (HKCU).
#
# PENTING: Jalankan skrip ini saat login dengan AKUN SISWA yang terkunci,
# atau lewat menu "Run" di akun tersebut. Karena kebijakan hanya dipasang
# di HKCU (per akun), akun Admin IT selalu bisa login dan memperbaiki.
#
# Pakai:
#   powershell -ExecutionPolicy Bypass -File unlock-admin.ps1
#   powershell -ExecutionPolicy Bypass -File unlock-admin.ps1 -FullUninstall

param(
    [switch]$FullUninstall
)

$ErrorActionPreference = 'Continue'

$targets = @(
    @{ Path = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\System';   Names = @('DisableTaskMgr', 'DisableRegistryTools') },
    @{ Path = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer'; Names = @('NoRun', 'NoControlPanel', 'DisallowRun') },
    @{ Path = 'HKCU:\Software\Policies\Microsoft\Windows\System';                  Names = @('DisableCMD') }
)

foreach ($target in $targets) {
    foreach ($name in $target.Names) {
        Remove-ItemProperty -Path $target.Path -Name $name -ErrorAction SilentlyContinue
    }
}

Remove-Item 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer\DisallowRun' -Recurse -Force -ErrorAction SilentlyContinue

Write-Host '[unlock] Kebijakan kiosk (Task Manager, CMD, Regedit, Run, Control Panel) telah dihapus.' -ForegroundColor Green
Write-Host '[unlock] Log off / log in ulang agar perubahan berlaku penuh.' -ForegroundColor Yellow

if ($FullUninstall) {
    schtasks /Delete /F /TN "PELOG Kiosk (Logon)" | Out-Null
    schtasks /Delete /F /TN "PELOG Kiosk (Watchdog)" | Out-Null
    schtasks /Delete /F /TN "PelogHardeningSuspend" | Out-Null
    schtasks /Delete /F /TN "PelogHardeningApply" | Out-Null
    schtasks /Delete /F /TN "PelogAutoUpdate" | Out-Null

    Remove-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run' -Name 'PelogKiosk' -ErrorAction SilentlyContinue

    Write-Host '[unlock] Autostart & scheduled task PELOG telah dihapus (full uninstall).' -ForegroundColor Green
}

Write-Host ''
Write-Host 'Catatan: aplikasi PELOG tetap terpasang. Uninstall lewat Settings > Apps,'
Write-Host 'atau hentikan proses PelogKiosk lalu hapus foldernya bila ingin bersih total.'
