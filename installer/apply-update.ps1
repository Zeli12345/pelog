# PELOG - Agen pembaruan otomatis.
# Dijalankan sebagai SYSTEM oleh Scheduled Task "PelogAutoUpdate" (trigger: boot + delay).
#
# Alur: kiosk mengunduh installer + menulis update.json (staging). Saat boot,
# skrip ini memverifikasi SHA-256 lalu memasang installer secara SENYAP
# (/VERYSILENT) - tanpa UAC dan tanpa dialog apa pun. Bila kiosk sedang
# berjalan (sesi berlangsung), update DITUNDA ke boot berikutnya agar siswa
# tidak terganggu; aplikasi lama tetap berjalan sampai laptop dimatikan.
#
# Manifest diperlakukan TIDAK terpercaya: installer_path wajib berada di dalam
# folder staging (data\updates) dan sha256 wajib 64 hex yang cocok.
#
# Hasil setiap percobaan ditulis ke:
#   C:\ProgramData\PELOG\update-result.json
#   C:\ProgramData\PELOG\update-agent.log

param(
    [string]$ConfigDir = (Join-Path $env:ProgramData 'PELOG')
)

$ErrorActionPreference = 'Stop'

$manifestPath = Join-Path $ConfigDir 'update.json'
$resultPath = Join-Path $ConfigDir 'update-result.json'
$attemptsPath = Join-Path $ConfigDir 'update-attempts.json'
$logPath = Join-Path $ConfigDir 'update-agent.log'
$installLog = Join-Path $ConfigDir 'update-install.log'

function Write-Log([string]$Message) {
    try {
        Add-Content -Path $logPath -Value ("{0} {1}" -f (Get-Date -Format o), $Message) -ErrorAction SilentlyContinue
    } catch {
        # logging opsional
    }
}

function Write-Result([string]$Result, [string]$Version, [string]$Detail = '', [int]$Attempts = 0) {
    $payload = @{
        result      = $Result
        version     = $Version
        detail      = $Detail
        attempts    = $Attempts
        finished_at = (Get-Date -Format o)
    } | ConvertTo-Json -Compress

    try {
        Set-Content -Path $resultPath -Value $payload -Encoding UTF8 -ErrorAction SilentlyContinue
    } catch {
        # hasil opsional
    }
}

function Get-Attempts([string]$Version) {
    try {
        if (Test-Path $attemptsPath) {
            $state = Get-Content -Path $attemptsPath -Raw | ConvertFrom-Json

            if ($null -ne $state -and ([string]$state.version) -eq $Version) {
                return [int]$state.count
            }
        }
    } catch {
        # state rusak - anggap nol
    }

    return 0
}

function Set-Attempts([string]$Version, [int]$Count) {
    try {
        $payload = @{ version = $Version; count = $Count } | ConvertTo-Json -Compress
        Set-Content -Path $attemptsPath -Value $payload -Encoding UTF8 -ErrorAction SilentlyContinue
    } catch {
        # state opsional
    }
}

function Clear-Staging([string]$InstallerPath) {
    if (-not [string]::IsNullOrWhiteSpace($InstallerPath)) {
        Remove-Item -Path $InstallerPath -Force -ErrorAction SilentlyContinue
    }

    Remove-Item -Path $manifestPath -Force -ErrorAction SilentlyContinue
    Remove-Item -Path $attemptsPath -Force -ErrorAction SilentlyContinue
}

# Wajib berjalan dengan hak administrator (SYSTEM selalu memenuhi).
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Log 'batal: bukan administrator/SYSTEM'
    exit 1
}

if (-not (Test-Path $manifestPath)) {
    exit 0
}

$manifest = $null
try {
    $manifest = Get-Content -Path $manifestPath -Raw | ConvertFrom-Json
} catch {
    $manifest = $null
}

if ($null -eq $manifest -or [string]::IsNullOrWhiteSpace($manifest.version) -or [string]::IsNullOrWhiteSpace($manifest.installer_path)) {
    Write-Log 'manifest tidak valid - dibersihkan'
    Clear-Staging $null
    exit 0
}

$version = [string]$manifest.version
$installer = [string]$manifest.installer_path

# Tunda bila kiosk sedang berjalan (sesi aktif) - dicoba lagi pada boot berikutnya.
if (Get-Process -Name 'PelogKiosk' -ErrorAction SilentlyContinue) {
    Write-Log ("tunda v{0}: kiosk sedang berjalan" -f $version)
    Write-Result 'deferred' $version 'kiosk masih berjalan'
    exit 0
}

# Validasi installer_path: wajib absolut lokal (drive letter), bukan UNC/relatif,
# dan wajib berada di dalam folder staging milik kiosk (data\updates).
$stagingRoot = [IO.Path]::GetFullPath((Join-Path $ConfigDir 'data\updates')).TrimEnd('\') + '\'
$installerValid = $false

if ($installer -match '^[A-Za-z]:\\') {
    try {
        $candidate = [IO.Path]::GetFullPath($installer)

        if ($candidate.StartsWith($stagingRoot, [StringComparison]::OrdinalIgnoreCase) -and
            [IO.Path]::GetExtension($candidate).ToLowerInvariant() -eq '.exe') {
            $installer = $candidate
            $installerValid = $true
        }
    } catch {
        $installerValid = $false
    }
}

if (-not $installerValid) {
    # File di luar staging TIDAK dihapus (path tidak terpercaya) - cukup manifest.
    Write-Log ("batal v{0}: installer_path tidak valid / di luar staging" -f $version)
    Write-Result 'missing_installer' $version $installer
    Clear-Staging $null
    exit 0
}

# sha256 wajib ada dan berformat 64 karakter hex; hash kosong/kurang BUKAN lolos.
if (([string]$manifest.sha256) -notmatch '^[0-9a-fA-F]{64}$') {
    Write-Log ("batal v{0}: sha256 hilang / tidak valid" -f $version)
    Write-Result 'hash_mismatch' $version 'sha256 tidak valid'
    Clear-Staging $installer
    exit 0
}

if (-not (Test-Path -LiteralPath $installer)) {
    Write-Log ("batal v{0}: installer tidak ditemukan" -f $version)
    Write-Result 'missing_installer' $version $installer
    Clear-Staging $installer
    exit 0
}

$fileInfo = Get-Item -LiteralPath $installer

# Verifikasi ulang SHA-256 (anti-tamper) sebelum mengeksekusi installer.
$hash = (Get-FileHash -LiteralPath $installer -Algorithm SHA256).Hash.ToLowerInvariant()
$expected = ([string]$manifest.sha256).ToLowerInvariant()

if ($hash -ne $expected) {
    Write-Log ("batal v{0}: hash tidak cocok ({1})" -f $version, $hash)
    Write-Result 'hash_mismatch' $version $hash
    Clear-Staging $installer
    exit 0
}

# Cek sekali lagi: hindari balapan dengan autostart/watchdog kiosk.
if (Get-Process -Name 'PelogKiosk' -ErrorAction SilentlyContinue) {
    Write-Log ("tunda v{0}: kiosk muncul saat verifikasi" -f $version)
    Write-Result 'deferred' $version 'kiosk mulai berjalan'
    exit 0
}

# Catat identitas file yang lolos verifikasi sebelum dijalankan (jejak audit).
Write-Log ("terverifikasi v{0}: {1} ({2} byte) sha256={3}" -f $version, $fileInfo.Name, $fileInfo.Length, $hash)
Write-Log ("memasang v{0} dari {1}" -f $version, $installer)

$attempts = Get-Attempts $version

try {
    $process = Start-Process -FilePath $installer `
        -ArgumentList '/VERYSILENT', '/NORESTART', '/SUPPRESSMSGBOXES', '/NOCLOSEAPPLICATIONS', "/LOG=$installLog" `
        -WindowStyle Hidden -Wait -PassThru

    $exitCode = $process.ExitCode
} catch {
    $exitCode = -1
    Write-Log ("error v{0}: {1}" -f $version, $_.Exception.Message)
}

if ($exitCode -eq 0) {
    Write-Log ("sukses v{0}" -f $version)
    Write-Result 'installed' $version 'aktif setelah restart berikutnya' 0
    Clear-Staging $installer
    exit 0
}

# Gagal: beri kesempatan ulang pada boot berikutnya, maksimal 3x.
$attempts++

if ($attempts -ge 3) {
    Write-Log ("gagal permanen v{0} setelah {1}x percobaan" -f $version, $attempts)
    Write-Result 'failed_permanent' $version ("exit code " + $exitCode) $attempts
    Clear-Staging $installer
    exit 1
}

Set-Attempts $version $attempts
Write-Log ("gagal v{0} (percobaan {1}/3, exit code {2}) - dicoba lagi saat boot berikutnya" -f $version, $attempts, $exitCode)
Write-Result 'failed' $version ("exit code " + $exitCode) $attempts

exit 0
