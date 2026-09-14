# BALI-LOG - Menjalankan server staging (Laravel) di port 8000.
# Pakai: .\scripts\dev-serve.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

& "$PSScriptRoot\env.ps1"
& "$PSScriptRoot\dev-db.ps1"

Set-Location (Join-Path $root 'server')
Write-Host "[serve] Laravel berjalan di http://0.0.0.0:8000 (LAN: http://<IP-PC>:8000)" -ForegroundColor Cyan
php artisan serve --host=0.0.0.0 --port=8000
