# PELOG - Development Environment
# Mengarahkan semua cache & output berat ke drive E: (drive C: terbatas).
# Pakai: . .\scripts\env.ps1

$ErrorActionPreference = 'Stop'

$root  = Split-Path -Parent $PSScriptRoot
$cache = 'E:\pelog-cache'
$build = 'E:\pelog-build'

foreach ($d in @(
    $cache,
    "$cache\nuget",
    "$cache\dotnet",
    "$cache\composer",
    "$cache\npm",
    "$cache\temp",
    $build,
    "$build\publish",
    "$build\installer"
)) {
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Path $d -Force | Out-Null }
}

$env:NUGET_PACKAGES      = "$cache\nuget"
$env:DOTNET_CLI_HOME     = "$cache\dotnet"
$env:COMPOSER_CACHE_DIR  = "$cache\composer"
$env:npm_config_cache    = "$cache\npm"
$env:TEMP                = "$cache\temp"
$env:TMP                 = "$cache\temp"

Write-Host "[env] PELOG dev environment aktif" -ForegroundColor Cyan
Write-Host "[env] Project        : $root"
Write-Host "[env] NUGET_PACKAGES : $env:NUGET_PACKAGES"
Write-Host "[env] COMPOSER_CACHE : $env:COMPOSER_CACHE_DIR"
Write-Host "[env] npm cache      : $env:npm_config_cache"
Write-Host "[env] TEMP/TMP       : $env:TEMP"
Write-Host "[env] Build output   : $build"
