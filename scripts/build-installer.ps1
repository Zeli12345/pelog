# BALI-LOG - Build installer (Inno Setup) memakai hasil publish di E:
# Pakai: .\scripts\build-installer.ps1 -BaseUrl "http://192.168.1.10:8000" -EnrollmentCode "BLG-XXXX-XXXX"

param(
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [string]$EnrollmentCode = ""
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$installerDir = Join-Path $root "installer"

if (-not (Test-Path "E:\balilog-build\publish\BalilogKiosk.exe")) {
    throw "Hasil publish tidak ditemukan. Jalankan dulu: .\scripts\build-client.ps1"
}

# Konfigurasi awal aplikasi client (dibaca dari C:\ProgramData\BALI-LOG\balilog.json)
$clientConfig = [ordered]@{
    base_url          = $BaseUrl
    enrollment_code   = ""
    test_mode         = $false
    hardening_enabled = $true
} | ConvertTo-Json

[System.IO.File]::WriteAllText((Join-Path $installerDir "balilog.client.json"), $clientConfig)

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

& $iscc (Join-Path $installerDir "balilog-setup.iss")

if ($LASTEXITCODE -ne 0) {
    throw "Compile installer gagal (exit code $LASTEXITCODE)"
}

$setup = "E:\balilog-build\installer\BALI-LOG_Setup.exe"

if (-not (Test-Path $setup)) {
    throw "Installer tidak terbentuk: $setup"
}

$size = [math]::Round((Get-Item $setup).Length / 1MB, 1)

Write-Host "[installer] Selesai: $setup ($size MB)" -ForegroundColor Green
