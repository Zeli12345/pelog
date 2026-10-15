# PELOG - Build alat perbaikan (anti-laptop-terkunci) PELOG-Revert.exe.
# Hasil: E:\pelog-build\revert\PELOG-Revert.exe + salinan ke repo releases\tools\.
# Pakai: .\scripts\build-revert.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot

dotnet publish "$root\client\PelogRevert\PelogRevert.csproj" `
    -c Release -r win-x64 --self-contained true `
    -p:PublishSingleFile=true `
    -p:IncludeNativeLibrariesForSelfExtract=true `
    -p:EnableCompressionInSingleFile=true

if ($LASTEXITCODE -ne 0) {
    throw "Publish PELOG-Revert gagal (exit code $LASTEXITCODE)"
}

$candidates = @(
    (Join-Path "E:\pelog-build" "client\bin\PelogRevert\Release\net8.0-windows\win-x64\publish\PELOG-Revert.exe"),
    (Join-Path $root "client\PelogRevert\bin\Release\net8.0-windows\win-x64\publish\PELOG-Revert.exe")
)

$built = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $built) {
    throw "Publish output tidak ditemukan (dicari: $($candidates -join ' | '))"
}

$destDir = Join-Path $root "releases\tools"
New-Item -ItemType Directory -Force -Path $destDir | Out-Null
Copy-Item $built (Join-Path $destDir "PELOG-Revert.exe") -Force

$hash = (Get-FileHash $built -Algorithm SHA256).Hash.ToLower()
$size = [math]::Round((Get-Item $built).Length / 1MB, 1)

Write-Host "[revert] Selesai: $destDir\PELOG-Revert.exe ($size MB)" -ForegroundColor Green
Write-Host "[revert] sha256: $hash"
