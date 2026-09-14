# BALI-LOG - Menyalakan MariaDB XAMPP (port 3307) jika belum berjalan.
# Pakai: .\scripts\dev-db.ps1

$ErrorActionPreference = 'Continue'
$port   = 3307
$mysqld = 'C:\xampp83\mysql\bin\mysqld.exe'
$myini  = 'C:\xampp83\mysql\bin\my.ini'

$up = (Test-NetConnection -ComputerName 127.0.0.1 -Port $port -WarningAction SilentlyContinue).TcpTestSucceeded
if ($up) {
    Write-Host "[db] MariaDB sudah jalan di 127.0.0.1:$port" -ForegroundColor Green
    exit 0
}

if (-not (Test-Path $mysqld)) {
    Write-Host "[db] mysqld tidak ditemukan: $mysqld" -ForegroundColor Red
    exit 1
}

Start-Process -FilePath $mysqld -ArgumentList @("--defaults-file=$myini") -WindowStyle Hidden

for ($i = 0; $i -lt 30; $i++) {
    Start-Sleep -Seconds 2
    $up = (Test-NetConnection -ComputerName 127.0.0.1 -Port $port -WarningAction SilentlyContinue).TcpTestSucceeded
    if ($up) { break }
}

if ($up) {
    Write-Host "[db] MariaDB siap di 127.0.0.1:$port" -ForegroundColor Green
} else {
    Write-Host "[db] GAGAL menyalakan MariaDB - cek log di C:\xampp83\mysql\data\*.err" -ForegroundColor Red
    exit 1
}
