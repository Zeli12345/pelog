# BALI-LOG - Kebijakan penguncian kiosk untuk AKUN SAAT INI.
# WAJIB dijalankan dengan hak Administrator (installer / UAC):
#   powershell -ExecutionPolicy Bypass -File hardening.ps1 -Apply
#   powershell -ExecutionPolicy Bypass -File hardening.ps1 -Suspend   (mode maintenance)

param(
    [switch]$Apply,
    [switch]$Suspend
)

$ErrorActionPreference = 'Continue'

$polSystem   = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\System'
$polExplorer = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer'
$polCmd      = 'HKCU:\Software\Policies\Microsoft\Windows\System'
$disallow    = "$polExplorer\DisallowRun"

$disallowedPrograms = @(
    'powershell.exe', 'pwsh.exe', 'wt.exe', 'cmd.exe',
    'regedit.exe', 'taskmgr.exe', 'mmc.exe', 'msconfig.exe', 'control.exe'
)

function Set-Dword($path, $name, $value) {
    if (-not (Test-Path $path)) { New-Item -Path $path -Force | Out-Null }
    New-ItemProperty -Path $path -Name $name -Value $value -PropertyType DWord -Force | Out-Null
}

function Remove-Value($path, $name) {
    Remove-ItemProperty -Path $path -Name $name -ErrorAction SilentlyContinue
}

if ($Apply) {
    Set-Dword $polSystem 'DisableTaskMgr' 1
    Set-Dword $polSystem 'DisableRegistryTools' 1
    Set-Dword $polExplorer 'NoRun' 1
    Set-Dword $polExplorer 'NoControlPanel' 1

    # Path kanonik untuk "Prevent access to the command prompt"
    Set-Dword $polCmd 'DisableCMD' 1

    # Daftar program yang dilarang (per pengguna)
    Set-Dword $polExplorer 'DisallowRun' 1

    if (Test-Path $disallow) { Remove-Item $disallow -Recurse -Force -ErrorAction SilentlyContinue }
    New-Item -Path $disallow -Force | Out-Null

    $index = 1
    foreach ($program in $disallowedPrograms) {
        New-ItemProperty -Path $disallow -Name "$index" -Value $program -PropertyType String -Force | Out-Null
        $index++
    }

    Write-Host '[hardening] Kebijakan kiosk DITERAPKAN untuk akun: ' -NoNewline
    Write-Host $env:USERNAME -ForegroundColor Green
    exit 0
}

if ($Suspend) {
    Remove-Value $polSystem 'DisableTaskMgr'
    Remove-Value $polSystem 'DisableRegistryTools'
    Remove-Value $polExplorer 'NoRun'
    Remove-Value $polExplorer 'NoControlPanel'
    Remove-Value $polCmd 'DisableCMD'
    Remove-Value $polExplorer 'DisallowRun'

    Remove-Item $disallow -Recurse -Force -ErrorAction SilentlyContinue

    Write-Host '[hardening] Kebijakan kiosk DITANGGUHKAN untuk akun: ' -NoNewline
    Write-Host $env:USERNAME -ForegroundColor Yellow
    exit 0
}

Write-Host 'Gunakan -Apply atau -Suspend.'
exit 1
