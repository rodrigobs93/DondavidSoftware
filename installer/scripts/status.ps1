<#
.SYNOPSIS
  Print a short status table: Postgres / Laravel / Worker + PIDs.
#>

$InstallRoot = if ($env:MIPOS_ROOT) { $env:MIPOS_ROOT } else { 'C:\MiPOS' }
$Port        = if ($env:MIPOS_PORT) { [int]$env:MIPOS_PORT } else { 8000 }
$ServiceName = 'MiPOSPostgres'

function Row([string]$Name, [string]$State, [string]$Extra) {
    '{0,-12} {1,-10} {2}' -f $Name, $State, $Extra
}

# Postgres
$svc = Get-Service $ServiceName -ErrorAction SilentlyContinue
if ($svc) { Row 'Postgres' $svc.Status ('service=' + $ServiceName) }
else      { Row 'Postgres' 'MISSING' 'service not installed' }

# Laravel
$laravel = Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
           Where-Object { $_.CommandLine -and ($_.CommandLine -match "artisan\s+serve.*--port=$Port") } |
           Select-Object -First 1
if ($laravel) { Row 'Laravel' 'Running' "pid=$($laravel.ProcessId) port=$Port" }
else          { Row 'Laravel' 'Stopped' '' }

# Worker
$worker = Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
          Where-Object { $_.CommandLine -and ($_.CommandLine -match 'app:print-worker') } |
          Select-Object -First 1
if ($worker) { Row 'Worker' 'Running' "pid=$($worker.ProcessId)" }
else         { Row 'Worker' 'Stopped' '' }

Write-Host ''
Write-Host "Logs: $InstallRoot\logs"
