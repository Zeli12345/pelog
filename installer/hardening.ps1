# PELOG - Kebijakan penguncian kiosk untuk AKUN SAAT INI.
# WAJIB dijalankan dengan hak Administrator (installer / UAC):
#   powershell -ExecutionPolicy Bypass -File hardening.ps1 -Apply
#   powershell -ExecutionPolicy Bypass -File hardening.ps1 -Suspend   (mode maintenance)
#   powershell -ExecutionPolicy Bypass -File hardening.ps1 -Wipe      (self-wipe perangkat)

param(
    [switch]$Apply,
    [switch]$Suspend,
    [switch]$Wipe
)

$ErrorActionPreference = 'Continue'

# Log publik self-wipe (dibaca Admin IT setelah perangkat dihapus dari dashboard).
$WipeLogPath = 'C:\Users\Public\pelog-wipe.log'

function Write-WipeLog([string]$Message) {
    try {
        if (-not (Test-Path -LiteralPath $WipeLogPath)) {
            New-Item -Path $WipeLogPath -ItemType File -Force -ErrorAction SilentlyContinue | Out-Null
        }

        $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
        Add-Content -LiteralPath $WipeLogPath -Value $line -Encoding ASCII -ErrorAction SilentlyContinue
    } catch {
        # logging tidak boleh menggagalkan wipe
    }
}

$polSystem   = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\System'
$polExplorer = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer'
$polCmd      = 'HKCU:\Software\Policies\Microsoft\Windows\System'
$disallow    = "$polExplorer\DisallowRun"

$disallowedPrograms = @(
    'powershell.exe', 'pwsh.exe', 'wt.exe', 'cmd.exe',
    'regedit.exe', 'taskmgr.exe', 'mmc.exe', 'msconfig.exe', 'control.exe'
)

function Set-Dword($path, $name, $value) {
    if (-not (Test-Path $path)) { New-Item -Path $path -Force | Out-Null }
    New-ItemProperty -Path $path -Name $name -Value $value -PropertyType DWord -Force | Out-Null
}

function Remove-Value($path, $name) {
    Remove-ItemProperty -Path $path -Name $name -ErrorAction SilentlyContinue
}

# ---------------------------------------------------------------------------
# SELF-WIPE: menghapus seluruh instalasi PELOG + data tanpa prompt UAC.
# Dipicu dari dashboard (perangkat dihapus) lewat Scheduled Task
# "BALILogSelfWipe" -> hardening.ps1 -Wipe. Semua langkah best-effort:
# tidak pernah melempar error dan setiap tahap dicatat ke log publik.
# ---------------------------------------------------------------------------
if ($Wipe) {
    Write-WipeLog 'permintaan self-wipe diterima (tahap 1).'

    # 1) Hentikan kiosk (dua kali, jeda singkat).
    $killAttempt = 0
    while ($killAttempt -lt 2) {
        $killAttempt++
        try {
            Write-WipeLog ('menghentikan PelogKiosk.exe (percobaan ' + $killAttempt + ')...')
            & taskkill.exe /f /im PelogKiosk.exe 2>&1 | Out-Null
            Write-WipeLog ('taskkill selesai dengan kode ' + $LASTEXITCODE + '.')
        } catch {
            Write-WipeLog ('GAGAL menghentikan kiosk: ' + $_.Exception.Message)
        }

        if ($killAttempt -lt 2) {
            Start-Sleep -Seconds 2
        }
    }

    # 2) Hapus Scheduled Task (watchdog/logon/auto-update/hardening lebih dulu,
    #    task self-wipe PALING AKHIR).
    $wipeTasks = @(
        'PELOG Kiosk (Watchdog)',
        'PELOG Kiosk (Logon)',
        'BALILogAutoUpdate',
        'BALILogHardeningApply',
        'BALILogHardeningSuspend',
        'BALILogSelfWipe'
    )

    foreach ($taskName in $wipeTasks) {
        try {
            Write-WipeLog ('menghapus task: ' + $taskName)
            & schtasks.exe /Delete /F /TN $taskName 2>&1 | Out-Null
            Write-WipeLog ('schtasks /Delete "' + $taskName + '" selesai dengan kode ' + $LASTEXITCODE + '.')
        } catch {
            Write-WipeLog ('GAGAL menghapus task ' + $taskName + ': ' + $_.Exception.Message)
        }
    }

    # 3) Hapus autostart HKLM.
    try {
        Remove-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run' -Name 'BALILogKiosk' -ErrorAction SilentlyContinue
        Write-WipeLog 'nilai autostart BALILogKiosk (HKLM Run) dihapus.'
    } catch {
        Write-WipeLog ('GAGAL menghapus autostart: ' + $_.Exception.Message)
    }

    # 4) Bersihkan kebijakan kiosk HKCU (best-effort, sama seperti -Suspend)
    #    supaya akun siswa tidak terkunci setelah aplikasi dihapus.
    try {
        Remove-Value $polSystem 'DisableTaskMgr'
        Remove-Value $polSystem 'DisableRegistryTools'
        Remove-Value $polExplorer 'NoRun'
        Remove-Value $polExplorer 'NoControlPanel'
        Remove-Value $polCmd 'DisableCMD'
        Remove-Value $polExplorer 'DisallowRun'
        Remove-Item $disallow -Recurse -Force -ErrorAction SilentlyContinue
        Write-WipeLog 'kebijakan kiosk HKCU dibersihkan.'
    } catch {
        Write-WipeLog ('GAGAL membersihkan kebijakan HKCU: ' + $_.Exception.Message)
    }

    # 5) Tahap 2: salin logika wipe ke TEMP dan jalankan lepas dari folder
    #    aplikasi supaya uninstaller tidak terkunci oleh skrip ini. Proses
    #    anak mewarisi token admin dari task (tanpa prompt UAC).
    try {
        $stage2Path = Join-Path $env:TEMP 'pelog-wipe-run.ps1'

        $stage2Script = @'
# PELOG self-wipe tahap 2 - dijalankan lepas dari folder aplikasi.
# Semua langkah best-effort; setiap tahap dicatat ke log publik.
$ErrorActionPreference = "Continue"
$log = "C:\Users\Public\pelog-wipe.log"
$appDir = Join-Path $env:ProgramFiles "PELOG Kiosk"
$dataDir = "C:\ProgramData\PELOG"

function Write-Stage2Log([string]$message) {
    try {
        if (-not (Test-Path -LiteralPath $log)) {
            New-Item -Path $log -ItemType File -Force -ErrorAction SilentlyContinue | Out-Null
        }

        $line = "{0} {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $message
        Add-Content -LiteralPath $log -Value $line -Encoding ASCII -ErrorAction SilentlyContinue
    } catch {
    }
}

Write-Stage2Log "tahap 2 dimulai: menunggu 3 detik agar kiosk berhenti..."
Start-Sleep -Seconds 3

try {
    $uninstaller = Join-Path $appDir "unins000.exe"

    if (Test-Path -LiteralPath $uninstaller) {
        Write-Stage2Log ("tahap 2: menjalankan uninstaller senyap: " + $uninstaller)
        $proc = Start-Process -FilePath $uninstaller -ArgumentList "/VERYSILENT", "/SUPPRESSMSGBOXES", "/NORESTART", "/NOCLOSEAPPLICATIONS" -WindowStyle Hidden -PassThru

        if ($null -ne $proc) {
            if ($proc.WaitForExit(300000)) {
                Write-Stage2Log ("tahap 2: uninstaller selesai (kode " + $proc.ExitCode + ").")
            } else {
                Write-Stage2Log "tahap 2: uninstaller belum selesai setelah 5 menit; dihentikan."
                try { $proc.Kill() } catch { }
            }
        } else {
            Write-Stage2Log "tahap 2: uninstaller tidak dapat dipantau (lanjut)."
        }
    } else {
        Write-Stage2Log ("tahap 2: uninstaller tidak ditemukan di " + $uninstaller + " (lanjut).")
    }
} catch {
    Write-Stage2Log ("tahap 2 GAGAL menjalankan uninstaller: " + $_.Exception.Message)
}

Start-Sleep -Seconds 2

try {
    if (Test-Path -LiteralPath $dataDir) {
        Write-Stage2Log ("tahap 2: menghapus folder data " + $dataDir + " ...")
        Remove-Item -LiteralPath $dataDir -Recurse -Force -ErrorAction SilentlyContinue
    }
} catch {
    Write-Stage2Log ("tahap 2 GAGAL menghapus folder data: " + $_.Exception.Message)
}

if (Test-Path -LiteralPath $dataDir) {
    Write-Stage2Log "tahap 2: folder data masih ada; mencoba sekali lagi..."
    try {
        Remove-Item -LiteralPath $dataDir -Recurse -Force -ErrorAction SilentlyContinue
    } catch {
    }
}

if (Test-Path -LiteralPath $dataDir) {
    Write-Stage2Log "tahap 2: PERINGATAN - folder data masih tersisa."
} else {
    Write-Stage2Log "tahap 2: folder data sudah bersih."
}

Write-Stage2Log "wipe selesai"

try {
    Remove-Item -LiteralPath $PSCommandPath -Force -ErrorAction SilentlyContinue
} catch {
}
'@

        Set-Content -LiteralPath $stage2Path -Value $stage2Script -Encoding ASCII -Force
        Write-WipeLog ('tahap 2 disiapkan di ' + $stage2Path + '; menjalankan...')

        $psArgs = '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + $stage2Path + '"'
        Start-Process -FilePath 'powershell.exe' -ArgumentList $psArgs -WindowStyle Hidden

        Write-WipeLog 'tahap 2 berjalan di latar belakang; tahap 1 selesai.'
    } catch {
        Write-WipeLog ('GAGAL menyiapkan tahap 2: ' + $_.Exception.Message)
    }

    Write-Host '[wipe] Self-wipe tahap 1 selesai; penghapusan dilanjutkan di latar belakang.'
    exit 0
}

if ($Apply) {
    Set-Dword $polSystem 'DisableTaskMgr' 1
    Set-Dword $polSystem 'DisableRegistryTools' 1
    Set-Dword $polExplorer 'NoRun' 1
    Set-Dword $polExplorer 'NoControlPanel' 1

    # Path kanonik untuk "Prevent access to the command prompt"
    Set-Dword $polCmd 'DisableCMD' 1

    # Daftar program yang dilarang (per pengguna)
    Set-Dword $polExplorer 'DisallowRun' 1

    if (Test-Path $disallow) { Remove-Item $disallow -Recurse -Force -ErrorAction SilentlyContinue }
    New-Item -Path $disallow -Force | Out-Null

    $index = 1
    foreach ($program in $disallowedPrograms) {
        New-ItemProperty -Path $disallow -Name "$index" -Value $program -PropertyType String -Force | Out-Null
        $index++
    }

    Write-Host '[hardening] Kebijakan kiosk DITERAPKAN untuk akun: ' -NoNewline
    Write-Host $env:USERNAME -ForegroundColor Green
    exit 0
}

if ($Suspend) {
    Remove-Value $polSystem 'DisableTaskMgr'
    Remove-Value $polSystem 'DisableRegistryTools'
    Remove-Value $polExplorer 'NoRun'
    Remove-Value $polExplorer 'NoControlPanel'
    Remove-Value $polCmd 'DisableCMD'
    Remove-Value $polExplorer 'DisallowRun'

    Remove-Item $disallow -Recurse -Force -ErrorAction SilentlyContinue

    Write-Host '[hardening] Kebijakan kiosk DITANGGUHKAN untuk akun: ' -NoNewline
    Write-Host $env:USERNAME -ForegroundColor Yellow
    exit 0
}

Write-Host 'Gunakan -Apply, -Suspend, atau -Wipe.'
exit 1
