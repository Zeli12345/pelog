# PELOG - Build aplikasi kiosk (self-contained single-file) ke drive E:
# Pakai: .\scripts\build-client.ps1

param(
    [string]$Configuration = "Release"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot

& "$PSScriptRoot\env.ps1"

$publishDir = "E:\pelog-build\publish"

if (Test-Path $publishDir) {
    Remove-Item $publishDir -Recurse -Force
}

Write-Host "[build] Publish PELOG Kiosk ($Configuration, win-x64)..." -ForegroundColor Cyan

dotnet publish "$root\client\PelogKiosk\PelogKiosk.csproj" `
    -c $Configuration `
    -r win-x64 `
    --self-contained true `
    "-p:PublishSingleFile=true" `
    "-p:IncludeNativeLibrariesForSelfExtract=true" `
    -o $publishDir

if ($LASTEXITCODE -ne 0) {
    throw "Publish gagal (exit code $LASTEXITCODE)"
}

$exe = Join-Path $publishDir "PelogKiosk.exe"

if (-not (Test-Path $exe)) {
    throw "Executable tidak ditemukan: $exe"
}

$corePdb = Join-Path $publishDir "PelogKiosk.Core.pdb"

if (-not (Test-Path $corePdb)) {
    throw "Symbol file tidak ditemukan: $corePdb"
}

# Tampilkan versi rilis dari konstanta AppInfo.Version (file tidak diubah).
$appInfoPath = Join-Path $root "client\PelogKiosk.Core\AppInfo.cs"

if (-not (Test-Path $appInfoPath)) {
    throw "AppInfo.cs tidak ditemukan: $appInfoPath"
}

$appInfoText = Get-Content -Path $appInfoPath -Raw
$versionMatch = [regex]::Match($appInfoText, 'const\s+string\s+Version\s*=\s*"([^"]+)"')

if (-not $versionMatch.Success) {
    throw "Tidak bisa membaca AppInfo.Version dari: $appInfoPath"
}

$appVersion = $versionMatch.Groups[1].Value

# Cegah rilis dengan versi client/installer yang tidak sinkron.
$csproj = [xml](Get-Content -Path "$root\client\PelogKiosk\PelogKiosk.csproj" -Raw)
$csprojVersion = [string]$csproj.Project.PropertyGroup.Version

if ([version]$csprojVersion -ne [version]$appVersion) {
    throw "Versi tidak sinkron: AppInfo=$appVersion, csproj=$csprojVersion"
}

$issText = Get-Content -Path "$root\installer\pelog-setup.iss" -Raw
$issVersionMatch = [regex]::Match($issText, 'AppVersion\s*=\s*([0-9.]+)')

if (-not $issVersionMatch.Success -or [version]$issVersionMatch.Groups[1].Value -ne [version]$appVersion) {
    throw "Versi tidak sinkron: AppInfo=$appVersion, installer=$($issVersionMatch.Groups[1].Value)"
}

Write-Host "[build] Versi aplikasi (AppInfo.Version): $appVersion" -ForegroundColor Cyan

$size = [math]::Round((Get-Item $exe).Length / 1MB, 1)

Write-Host "[build] Selesai: $exe ($size MB)" -ForegroundColor Green
