<#
.SYNOPSIS
  One-click launcher for Don David POS. Idempotent: safe to double-click any
  number of times — already-running components are reused, not duplicated.

.DESCRIPTION
  1. Acquires an exclusive launcher lock (prevents near-simultaneous clicks
     from both deciding "nothing is running").
  2. Ensures DonDavidPostgres service is Running.
  3. Starts `php artisan serve` only if no matching PHP process owns the port.
  4. Starts `php artisan app:print-worker` only if not already running.
  5. Waits for HTTP 200 on the root URL, then opens the browser once.

.NOTES
  Logs: C:\DonDavid\logs\launcher.log, laravel-YYYY-MM-DD.log, worker-YYYY-MM-DD.log
  PIDs: C:\DonDavid\run\laravel.pid, worker.pid
#>

$ErrorActionPreference = 'Stop'

# --- Config (overridable via environment; defaults match install.ps1) ---
$InstallRoot = if ($env:DONDAVID_ROOT) { $env:DONDAVID_ROOT } else { 'C:\DonDavid' }
$Port        = if ($env:DONDAVID_PORT) { [int]$env:DONDAVID_PORT } else { 8000 }
$ServiceName = 'DonDavidPostgres'

$PhpExe   = Join-Path $InstallRoot 'php\php.exe'
$AppDir   = Join-Path $InstallRoot 'app'
$LogsDir  = Join-Path $InstallRoot 'logs'
$RunDir   = Join-Path $InstallRoot 'run'
$LockFile = Join-Path $RunDir 'launcher.lock'
$LaravelPidFile = Join-Path $RunDir 'laravel.pid'
$WorkerPidFile  = Join-Path $RunDir 'worker.pid'

New-Item -ItemType Directory -Force -Path $LogsDir,$RunDir | Out-Null

# --- Helpers ---
function Write-Launcher([string]$msg) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $msg"
    Add-Content -Path (Join-Path $LogsDir 'launcher.log') -Value $line
}

function Test-PidAlive([int]$Target) {
    try { [void](Get-Process -Id $Target -ErrorAction Stop); return $true }
    catch { return $false }
}

function Get-PidFromFile([string]$Path) {
    if (-not (Test-Path $Path)) { return $null }
    $raw = (Get-Content $Path -ErrorAction SilentlyContinue | Select-Object -First 1)
    if ($raw -match '^\d+$' -and (Test-PidAlive ([int]$raw))) { return [int]$raw }
    return $null
}

function Find-PhpProcess([string]$CmdLinePattern) {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object { $_.CommandLine -and ($_.CommandLine -match $CmdLinePattern) } |
        Select-Object -First 1
}

function Show-Error([string]$msg) {
    Write-Launcher "ERROR: $msg"
    Add-Type -AssemblyName PresentationFramework -ErrorAction SilentlyContinue
    [System.Windows.MessageBox]::Show($msg, 'Don David POS', 'OK', 'Error') | Out-Null
}

# --- Single-instance lock (file opened exclusive for the duration) ---
try {
    $lockStream = [System.IO.File]::Open($LockFile, 'Create', 'Write', 'None')
} catch {
    Write-Launcher 'Another launcher run is in progress, exiting.'
    exit 0
}

try {
    Write-Launcher "---- launcher start (port=$Port) ----"

    # --- 0. Installation sanity check ---
    if (-not (Test-Path $PhpExe)) {
        Show-Error "PHP not found at $PhpExe. Don David POS is not installed — run DonDavidSetup.exe first."
        throw 'PHP missing'
    }
    if (-not (Test-Path (Join-Path $AppDir 'artisan'))) {
        Show-Error "App not found at $AppDir. Don David POS is not installed — run DonDavidSetup.exe first."
        throw 'App missing'
    }

    # --- 1. Postgres ---
    $svc = Get-Service $ServiceName -ErrorAction SilentlyContinue
    if (-not $svc) {
        Show-Error "Postgres service '$ServiceName' not found. Don David POS is not installed — run DonDavidSetup.exe first."
        throw "Service $ServiceName not found"
    }
    if ($svc.Status -ne 'Running') {
        Write-Launcher "Starting Postgres service $ServiceName"
        Start-Service $ServiceName
        $svc.WaitForStatus('Running', '00:00:15')
    } else {
        Write-Launcher 'Postgres already running, skipped.'
    }

    # --- 2. Laravel server ---
    $laravelPid = Get-PidFromFile $LaravelPidFile
    if (-not $laravelPid) {
        $existing = Find-PhpProcess "artisan\s+serve.*--port=$Port"
        if ($existing) { $laravelPid = $existing.ProcessId }
    }

    $portOpen = $false
    try { $portOpen = (Test-NetConnection -ComputerName 'localhost' -Port $Port -WarningAction SilentlyContinue).TcpTestSucceeded }
    catch { $portOpen = $false }

    if ($laravelPid) {
        Write-Launcher "Laravel already running (pid=$laravelPid), skipped."
    } elseif ($portOpen) {
        Show-Error "Port $Port is already in use by another process (not our PHP). Free the port or change DONDAVID_PORT."
        throw 'Port conflict'
    } else {
        $today   = Get-Date -Format 'yyyy-MM-dd'
        $logOut  = Join-Path $LogsDir "laravel-$today.log"
        Write-Launcher "Starting Laravel on port $Port"
        $proc = Start-Process -FilePath $PhpExe `
            -ArgumentList @('artisan','serve',"--host=0.0.0.0","--port=$Port") `
            -WorkingDirectory $AppDir `
            -WindowStyle Hidden `
            -RedirectStandardOutput $logOut `
            -RedirectStandardError  $logOut `
            -PassThru
        $proc.Id | Out-File -FilePath $LaravelPidFile -Encoding ascii -Force
        $laravelPid = $proc.Id
        Write-Launcher "  spawned pid=$laravelPid"
    }

    # --- 3. Print worker ---
    $workerPid = Get-PidFromFile $WorkerPidFile
    if (-not $workerPid) {
        $existingW = Find-PhpProcess 'app:print-worker'
        if ($existingW) { $workerPid = $existingW.ProcessId }
    }
    if ($workerPid) {
        Write-Launcher "Worker already running (pid=$workerPid), skipped."
    } else {
        $today = Get-Date -Format 'yyyy-MM-dd'
        $logW  = Join-Path $LogsDir "worker-$today.log"
        Write-Launcher 'Starting print worker'
        $procW = Start-Process -FilePath $PhpExe `
            -ArgumentList @('artisan','app:print-worker') `
            -WorkingDirectory $AppDir `
            -WindowStyle Hidden `
            -RedirectStandardOutput $logW `
            -RedirectStandardError  $logW `
            -PassThru
        $procW.Id | Out-File -FilePath $WorkerPidFile -Encoding ascii -Force
        Write-Launcher "  spawned pid=$($procW.Id)"
    }

    # --- 4. Wait for HTTP readiness ---
    $url   = "http://localhost:$Port"
    $ready = $false
    for ($i = 0; $i -lt 30; $i++) {
        try {
            $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
            if ($r.StatusCode -eq 200) { $ready = $true; break }
        } catch { Start-Sleep -Milliseconds 500 }
    }
    if (-not $ready) {
        Show-Error "Don David POS did not respond on $url within 15s. Check $LogsDir\laravel-$(Get-Date -Format yyyy-MM-dd).log"
        throw 'Server did not start'
    }

    # --- 5. Open browser ---
    Write-Launcher "Opening $url"
    Start-Process $url
    Write-Launcher '---- launcher done ----'
}
catch {
    Write-Launcher "FAILED: $($_.Exception.Message)"
    throw
}
finally {
    if ($lockStream) { $lockStream.Close() }
    Remove-Item $LockFile -Force -ErrorAction SilentlyContinue
}
