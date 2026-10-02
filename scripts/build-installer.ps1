# PELOG - Build installer (Inno Setup) memakai hasil publish di E:
# Pakai: .\scripts\build-installer.ps1 -BaseUrl "http://192.168.1.10:8000" -EnrollmentCode "BLG-XXXX-XXXX" [-UpdateCheckHours 6]
#        [-DeviceLabel "LAB-BL-09"] [-Location "Lab RPL 1"]

param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [string]$EnrollmentCode = "",
    [int]$UpdateCheckHours = 0,
    [string]$DeviceLabel = "",
    [string]$Location = ""
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$installerDir = Join-Path $root "installer"

if (-not (Test-Path "E:\pelog-build\publish\PelogKiosk.exe")) {
    throw "Hasil publish tidak ditemukan. Jalankan dulu: .\scripts\build-client.ps1"
}

# Konfigurasi awal aplikasi client (dibaca dari C:\ProgramData\PELOG\pelog.json)
# device_label/device_location hanya ditulis bila diisi, dan update_check_hours
# hanya bila diminta (> 0), agar output default tidak berubah.
$clientConfig = [ordered]@{
    base_url          = $BaseUrl
    enrollment_code   = ""
    test_mode         = $false
    hardening_enabled = $true
}

if ($DeviceLabel -ne "") {
    $clientConfig['device_label'] = $DeviceLabel
}

if ($Location -ne "") {
    $clientConfig['device_location'] = $Location
}

if ($UpdateCheckHours -gt 0) {
    $clientConfig['update_check_hours'] = $UpdateCheckHours
}

$clientConfig = $clientConfig | ConvertTo-Json

[System.IO.File]::WriteAllText((Join-Path $installerDir "pelog.client.json"), $clientConfig)

# Kode enrollment opsional (agar dialog enroll terisi otomatis)
$enrollmentFile = Join-Path $installerDir "enrollment.txt"

if ($EnrollmentCode -ne "") {
    [System.IO.File]::WriteAllText($enrollmentFile, $EnrollmentCode)
    Write-Host "[installer] Kode enrollment disertakan: $EnrollmentCode" -ForegroundColor Cyan
}
elseif (Test-Path $enrollmentFile) {
    Remove-Item $enrollmentFile -Force
}

# Compile dengan Inno Setup
$iscc = Join-Path $env:LOCALAPPDATA "Programs\Inno Setup 6\ISCC.exe"

if (-not (Test-Path $iscc)) {
    $iscc = "C:\Program Files (x86)\Inno Setup 6\ISCC.exe"
}

if (-not (Test-Path $iscc)) {
    throw "ISCC.exe (Inno Setup 6) tidak ditemukan."
}

Write-Host "[installer] Compile setup.iss..." -ForegroundColor Cyan

& $iscc (Join-Path $installerDir "pelog-setup.iss")

if ($LASTEXITCODE -ne 0) {
    throw "Compile installer gagal (exit code $LASTEXITCODE)"
}

$setup = "E:\pelog-build\installer\PELOG_Setup.exe"

if (-not (Test-Path $setup)) {
    throw "Installer tidak terbentuk: $setup"
}

$size = [math]::Round((Get-Item $setup).Length / 1MB, 1)

Write-Host "[installer] Selesai: $setup ($size MB)" -ForegroundColor Green
