param(
    [string]$ConfigDir = (Join-Path $env:ProgramData 'PELOG')
)

$ErrorActionPreference = 'Stop'

$appDir = $PSScriptRoot
$exe = Join-Path $appDir 'PelogKiosk.exe'
$updatesDir = Join-Path $ConfigDir 'updates'
$statePath = Join-Path $updatesDir 'state.json'
$lockPath = Join-Path $updatesDir 'update.lock'
$logPath = Join-Path $updatesDir 'agent.log'
$installLog = Join-Path $updatesDir 'install.log'
$sessionMarker = Join-Path $ConfigDir 'data\session.active'
$maxAttempts = 3

New-Item -ItemType Directory -Force -Path $updatesDir | Out-Null

function Write-Log([string]$Message) {
    try {
        Add-Content -LiteralPath $logPath -Value ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message)
    } catch { }
}

function Get-BaseUrl {
    try {
        $value = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\PELOG' -Name BaseUrl -ErrorAction Stop).BaseUrl
        if (-not [string]::IsNullOrWhiteSpace($value)) { return $value.TrimEnd('/') }
    } catch { }

    return 'https://pelog.smkn1mas.sch.id'
}

function Get-InstalledVersion {
    try {
        $info = [System.Diagnostics.FileVersionInfo]::GetVersionInfo($exe)
        if (-not [string]::IsNullOrWhiteSpace($info.FileVersion)) { return [version]$info.FileVersion }
    } catch { }

    return [version]'0.0.0'
}

function Write-State([string]$State, [string]$Target, [string]$Sha = '', [Int64]$Size = 0, [string]$Installer = '', [int]$Attempts = 0, [string]$ErrorText = '') {
    $data = [ordered]@{
        state          = $State
        target_version = $Target
        sha256         = $Sha
        size           = $Size
        installer_path = $Installer
        attempts       = $Attempts
        last_error     = $ErrorText
        checked_at     = (Get-Date).ToUniversalTime().ToString('o')
    }

    $tmp = "$statePath.tmp"
    $data | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $tmp -Encoding UTF8
    Move-Item -LiteralPath $tmp -Destination $statePath -Force
}

function Remove-UpdateLock {
    Remove-Item -LiteralPath $lockPath -Force -ErrorAction SilentlyContinue
}

try {
    Write-Log 'agen update mulai'

    if (Test-Path -LiteralPath $lockPath) {
        Write-Log 'update.lock ada; keluar agar tidak dobel'
        exit 0
    }

    $installed = Get-InstalledVersion
    $baseUrl = Get-BaseUrl
    $manifestUrl = "$baseUrl/api/v1/version"

    try {
        $response = Invoke-RestMethod -Uri $manifestUrl -Method Get -TimeoutSec 20 -UseBasicParsing
        $release = $response.data
    } catch {
        Write-Log "server tidak terjangkau: $($_.Exception.Message)"
        exit 0
    }

    if ($null -eq $release -or [string]::IsNullOrWhiteSpace([string]$release.latest_version)) {
        Write-Log 'server tidak mengirim latest_version'
        exit 0
    }

    $target = [version]([string]$release.latest_version)
    $sha = ([string]$release.sha256).ToLowerInvariant()
    $size = [Int64]$release.size

    if ($sha -notmatch '^[0-9a-f]{64}$' -or $size -le 0) {
        Write-Log 'rilis tidak memiliki sha256/size yang valid; tidak memasang apa pun'
        Write-State 'up_to_date' $target.ToString() '' 0 '' 0 'Rilis tanpa hash valid'
        exit 0
    }

    if ($installed -ge $target) {
        Write-Log "sudah versi $installed (target $target)"
        Write-State 'up_to_date' $target.ToString()
        Remove-UpdateLock
        exit 0
    }

    if (Test-Path -LiteralPath $sessionMarker) {
        $age = (Get-Date) - (Get-Item -LiteralPath $sessionMarker).LastWriteTime
        if ($age.TotalMinutes -lt 10) {
            Write-Log 'sesi aktif; pembaruan ditunda sampai sesi selesai/boot berikutnya'
            Write-State 'deferred' $target.ToString() $sha $size '' 0 'Sesi kiosk masih aktif'
            exit 0
        }

        Remove-Item -LiteralPath $sessionMarker -Force -ErrorAction SilentlyContinue
        Write-Log 'session.active kedaluwarsa; dihapus'
    }

    $installer = Join-Path $updatesDir ("PELOG_Setup_{0}.exe" -f $target)
    $partial = "$installer.part"
    $attempts = 0
    $state = $null

    if (Test-Path -LiteralPath $statePath) {
        try {
            $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            if ($state.target_version -eq $target.ToString()) {
                $attempts = [int]$state.attempts
            }
        } catch { }
    }

    $needDownload = $true
    if (Test-Path -LiteralPath $statePath) {
        try {
            $existing = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            if ($existing.target_version -eq $target.ToString() -and $existing.sha256 -eq $sha -and (Test-Path -LiteralPath $installer)) {
                $hash = (Get-FileHash -LiteralPath $installer -Algorithm SHA256).Hash.ToLowerInvariant()
                if ($hash -eq $sha -and (Get-Item -LiteralPath $installer).Length -eq $size) {
                    $needDownload = $false
                    Write-Log 'artefak lama masih valid; memakai ulang'
                }
            }
        } catch { }
    }

    Write-State 'downloading' $target.ToString() $sha $size $installer $attempts

    if ($needDownload) {
        $downloadUrl = [string]$release.installer_url
        if ([string]::IsNullOrWhiteSpace($downloadUrl)) {
            $downloadUrl = "$baseUrl/downloads/client-setup"
        }

        Remove-Item -LiteralPath $partial -Force -ErrorAction SilentlyContinue
        Write-Log "mengunduh $downloadUrl"

        try {
            Invoke-WebRequest -Uri $downloadUrl -OutFile $partial -UseBasicParsing -TimeoutSec 900
        } catch {
            Remove-Item -LiteralPath $partial -Force -ErrorAction SilentlyContinue
            Write-Log "unduhan gagal: $($_.Exception.Message)"
            Write-State 'failed_retry' $target.ToString() $sha $size '' ($attempts + 1) 'Unduhan gagal'
            exit 0
        }

        $hash = (Get-FileHash -LiteralPath $partial -Algorithm SHA256).Hash.ToLowerInvariant()

        if ($hash -ne $sha -or (Get-Item -LiteralPath $partial).Length -ne $size) {
            Remove-Item -LiteralPath $partial -Force -ErrorAction SilentlyContinue
            Write-Log 'hash/ukuran tidak cocok; unduhan dibuang'
            Write-State 'failed_retry' $target.ToString() $sha $size '' ($attempts + 1) 'Hash installer tidak cocok'
            exit 0
        }

        Move-Item -LiteralPath $partial -Destination $installer -Force
    }

    Write-State 'staged' $target.ToString() $sha $size $installer $attempts

    if (Test-Path -LiteralPath $sessionMarker) {
        $age = (Get-Date) - (Get-Item -LiteralPath $sessionMarker).LastWriteTime
        if ($age.TotalMinutes -lt 10) {
            Write-Log 'sesi muncul saat verifikasi; tunda pemasangan'
            Write-State 'deferred' $target.ToString() $sha $size $installer $attempts 'Sesi kiosk masih aktif'
            exit 0
        }
    }

    Set-Content -LiteralPath $lockPath -Value (Get-Date).ToString('o') -Encoding UTF8
    Write-State 'installing' $target.ToString() $sha $size $installer $attempts

    Get-Process -Name 'PelogKiosk' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2

    $installerArgs = @(
        '/VERYSILENT',
        '/SUPPRESSMSGBOXES',
        '/NORESTART',
        '/CLOSEAPPLICATIONS',
        '/RESTARTAPPLICATIONS',
        "/LOG=$installLog"
    )

    $process = Start-Process -FilePath $installer -ArgumentList $installerArgs -WindowStyle Hidden -Wait -PassThru
    $exitCode = $process.ExitCode
    Write-Log "installer selesai dengan exit code $exitCode"

    if ($exitCode -eq 3010 -or $exitCode -eq 1641) {
        Write-State 'installed_pending_reboot' $target.ToString() $sha $size $installer $attempts 'Installer meminta restart'
        Remove-UpdateLock
        Write-Log 'installer meminta restart; kiosk akan diblokir sampai reboot'
        exit 0
    }

    $after = Get-InstalledVersion

    if ($after -ge $target) {
        Write-State 'installed' $target.ToString() $sha $size $installer $attempts
        Remove-UpdateLock
        Write-Log "berhasil terpasang versi $after"
        Remove-Item -LiteralPath $installer -Force -ErrorAction SilentlyContinue
        try {
            schtasks.exe /Run /TN 'PELOG Kiosk (Logon)' | Out-Null
        } catch { }
        exit 0
    }

    $attempts++
    Remove-UpdateLock

    if ($attempts -ge $maxAttempts) {
        Write-State 'failed_permanent' $target.ToString() $sha $size $installer $attempts 'Versi tidak berubah setelah instalasi'
        Write-Log 'gagal permanen setelah 3 percobaan'
        exit 1
    }

    Write-State 'failed_retry' $target.ToString() $sha $size $installer $attempts 'Versi tidak berubah setelah instalasi'
    Write-Log "gagal, percobaan $attempts/$maxAttempts"
    exit 0
} catch {
    Write-Log "error tak terduga: $($_.Exception.Message)"
    try {
        Write-State 'failed_retry' '' '' 0 '' 1 $_.Exception.Message
    } catch { }
    Remove-UpdateLock
    exit 1
}
