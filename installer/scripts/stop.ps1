<#
.SYNOPSIS
  Clean shutdown of Laravel server + print worker. Leaves Postgres running
  (it's a Windows service and restarts on reboot anyway).

.DESCRIPTION
  Kills by PID from run\*.pid first; falls back to command-line match for
  stale/missing PID files.
#>

$ErrorActionPreference = 'Continue'

$InstallRoot = if ($env:MIPOS_ROOT) { $env:MIPOS_ROOT } else { 'C:\MiPOS' }
$RunDir      = Join-Path $InstallRoot 'run'
$LaravelPidFile = Join-Path $RunDir 'laravel.pid'
$WorkerPidFile  = Join-Path $RunDir 'worker.pid'

function Stop-ByPidFile([string]$Path, [string]$Pattern, [string]$Label) {
    $killed = $false
    if (Test-Path $Path) {
        $raw = Get-Content $Path -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($raw -match '^\d+$') {
            try {
                Stop-Process -Id ([int]$raw) -Force -ErrorAction Stop
                Write-Host "$Label stopped (pid=$raw)"
                $killed = $true
            } catch {
                Write-Host "$Label pid $raw was already gone"
            }
        }
        Remove-Item $Path -Force -ErrorAction SilentlyContinue
    }
    if (-not $killed) {
        $stray = Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
                 Where-Object { $_.CommandLine -and ($_.CommandLine -match $Pattern) }
        foreach ($p in $stray) {
            try {
                Stop-Process -Id $p.ProcessId -Force -ErrorAction Stop
                Write-Host "$Label stray stopped (pid=$($p.ProcessId))"
            } catch { }
        }
    }
}

Stop-ByPidFile $LaravelPidFile 'artisan\s+serve'   'Laravel'
Stop-ByPidFile $WorkerPidFile  'app:print-worker'  'Worker'

Write-Host 'Done. Postgres service is left running.'
