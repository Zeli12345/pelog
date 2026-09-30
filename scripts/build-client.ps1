# BALI-LOG - Build aplikasi kiosk (self-contained single-file) ke drive E:
# Pakai: .\scripts\build-client.ps1

param(
    [string]$Configuration = "Release"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot

& "$PSScriptRoot\env.ps1"

$publishDir = "E:\balilog-build\publish"

if (Test-Path $publishDir) {
    Remove-Item $publishDir -Recurse -Force
}

Write-Host "[build] Publish BALI-LOG Kiosk ($Configuration, win-x64)..." -ForegroundColor Cyan

dotnet publish "$root\client\BalilogKiosk\BalilogKiosk.csproj" `
    -c $Configuration `
    -r win-x64 `
    --self-contained true `
    "-p:PublishSingleFile=true" `
    "-p:IncludeNativeLibrariesForSelfExtract=true" `
    -o $publishDir

if ($LASTEXITCODE -ne 0) {
    throw "Publish gagal (exit code $LASTEXITCODE)"
}

$exe = Join-Path $publishDir "BalilogKiosk.exe"

if (-not (Test-Path $exe)) {
    throw "Executable tidak ditemukan: $exe"
}

$corePdb = Join-Path $publishDir "BalilogKiosk.Core.pdb"

if (-not (Test-Path $corePdb)) {
    throw "Symbol file tidak ditemukan: $corePdb"
}

# Tampilkan versi rilis dari konstanta AppInfo.Version (file tidak diubah).
$appInfoPath = Join-Path $root "client\BalilogKiosk.Core\AppInfo.cs"

if (-not (Test-Path $appInfoPath)) {
    throw "AppInfo.cs tidak ditemukan: $appInfoPath"
}

$appInfoText = Get-Content -Path $appInfoPath -Raw
$versionMatch = [regex]::Match($appInfoText, 'const\s+string\s+Version\s*=\s*"([^"]+)"')

if (-not $versionMatch.Success) {
    throw "Tidak bisa membaca AppInfo.Version dari: $appInfoPath"
}

$appVersion = $versionMatch.Groups[1].Value

Write-Host "[build] Versi aplikasi (AppInfo.Version): $appVersion" -ForegroundColor Cyan

$size = [math]::Round((Get-Item $exe).Length / 1MB, 1)

Write-Host "[build] Selesai: $exe ($size MB)" -ForegroundColor Green
