<#
.SYNOPSIS
  Post-install bootstrap for Don David POS. Invoked by Inno Setup after file
  extraction. Idempotent — safe to re-run for upgrades.

.PARAMETER InstallRoot
  e.g. C:\DonDavid

.PARAMETER Port
  HTTP port for artisan serve.

.PARAMETER PrinterQueue
  Windows printer queue name (e.g. "XP-80C", "Microsoft Print to PDF").

.PARAMETER AdminEmail / AdminPassword
  First admin user credentials.

.PARAMETER CreateStartupShortcut
  If present, registers start.ps1 in shell:startup.
#>

param(
    [Parameter(Mandatory=$true)] [string]$InstallRoot,
    [Parameter(Mandatory=$true)] [int]$Port,
    [Parameter(Mandatory=$true)] [string]$PrinterQueue,
    [Parameter(Mandatory=$true)] [string]$AdminEmail,
    [Parameter(Mandatory=$true)] [string]$AdminPassword,
    [switch]$CreateStartupShortcut
)

$ErrorActionPreference = 'Stop'

# --- Paths ---
$AppDir    = Join-Path $InstallRoot 'app'
$PhpDir    = Join-Path $InstallRoot 'php'
$PhpExe    = Join-Path $PhpDir     'php.exe'
$PgDir     = Join-Path $InstallRoot 'pgsql'
$PgBin     = Join-Path $PgDir      'bin'
$PgData    = Join-Path $PgDir      'data'
$LogsDir   = Join-Path $InstallRoot 'logs'
$ScriptsDir = Join-Path $InstallRoot 'scripts'
$Composer  = Join-Path $InstallRoot 'composer.phar'

New-Item -ItemType Directory -Force -Path $LogsDir | Out-Null

function Log([string]$m) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $m"
    Write-Host $line
    Add-Content (Join-Path $LogsDir 'install.log') $line
}

function Run([string]$File, [string[]]$ArgList, [string]$Cwd) {
    Log "> $File $($ArgList -join ' ')"
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName  = $File
    $psi.Arguments = [string]::Join(' ', ($ArgList | ForEach-Object { if ($_ -match '\s') { "`"$_`"" } else { $_ } }))
    if ($Cwd) { $psi.WorkingDirectory = $Cwd }
    $psi.UseShellExecute        = $false
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError  = $true
    $p = [System.Diagnostics.Process]::Start($psi)
    $out = $p.StandardOutput.ReadToEnd()
    $err = $p.StandardError.ReadToEnd()
    $p.WaitForExit()
    if ($out) { Add-Content (Join-Path $LogsDir 'install.log') $out }
    if ($err) { Add-Content (Join-Path $LogsDir 'install.log') $err }
    if ($p.ExitCode -ne 0) { throw "Command failed ($($p.ExitCode)): $File $($ArgList -join ' ')`n$err" }
    return $out
}

function New-RandomPassword([int]$Len = 24) {
    $bytes = New-Object byte[] $Len
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    [Convert]::ToBase64String($bytes).Substring(0,$Len) -replace '[/+=]', 'x'
}

# --- 1. Sanity ---
Log '==== Don David POS install ===='
Log "Root=$InstallRoot Port=$Port Printer=$PrinterQueue Admin=$AdminEmail"

if (-not (Test-Path $PhpExe)) { throw "Portable PHP not found at $PhpExe" }
if (-not (Test-Path (Join-Path $PgBin 'initdb.exe'))) { throw "Portable Postgres not found at $PgBin" }
if (-not (Test-Path (Join-Path $AppDir 'artisan'))) { throw "App not found at $AppDir" }

# --- 2. Initialize Postgres data directory (first install only) ---
$ServiceName = 'DonDavidPostgres'
$svc = Get-Service $ServiceName -ErrorAction SilentlyContinue
if (-not (Test-Path (Join-Path $PgData 'PG_VERSION'))) {
    Log 'Running initdb (first install)…'
    Run (Join-Path $PgBin 'initdb.exe') @('-U','postgres','-A','trust','-E','UTF8','-D',$PgData) $null
}

# --- 3. Register + start the Postgres service ---
if (-not $svc) {
    Log "Registering service $ServiceName"
    Run (Join-Path $PgBin 'pg_ctl.exe') @('register','-N',$ServiceName,'-D',$PgData,'-S','auto','-w') $null
    $svc = Get-Service $ServiceName
}
if ($svc.Status -ne 'Running') {
    Start-Service $ServiceName
    $svc.WaitForStatus('Running','00:00:30')
}

# Wait for postgres to accept connections
for ($i=0; $i -lt 20; $i++) {
    & (Join-Path $PgBin 'pg_isready.exe') -h localhost -p 5432 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 1
}

# --- 4. Create DB + role (idempotent) ---
$DbName = 'don_david'
$DbUser = 'don_david_user'
$DbPassFile = Join-Path $InstallRoot 'run\db_password.txt'
New-Item -ItemType Directory -Force -Path (Split-Path $DbPassFile) | Out-Null

if (Test-Path $DbPassFile) {
    $DbPass = (Get-Content $DbPassFile -Raw).Trim()
    Log 'Reusing existing DB password from run\db_password.txt'
} else {
    $DbPass = New-RandomPassword 24
    Set-Content -Path $DbPassFile -Value $DbPass -Encoding ascii
    Log 'Generated new DB password'
}

$psql = Join-Path $PgBin 'psql.exe'
$escPass = $DbPass -replace "'", "''"
$sql = @"
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='$DbUser') THEN
    CREATE ROLE $DbUser LOGIN PASSWORD '$escPass';
  ELSE
    ALTER ROLE $DbUser WITH LOGIN PASSWORD '$escPass';
  END IF;
END \$\$;
"@
Run $psql @('-U','postgres','-h','localhost','-d','postgres','-v','ON_ERROR_STOP=1','-c',$sql) $null

$dbExists = (Run $psql @('-U','postgres','-h','localhost','-d','postgres','-tAc',"SELECT 1 FROM pg_database WHERE datname='$DbName'") $null).Trim()
if ($dbExists -ne '1') {
    Run $psql @('-U','postgres','-h','localhost','-d','postgres','-c',"CREATE DATABASE $DbName OWNER $DbUser") $null
    Log "Created database $DbName"
} else {
    Log "Database $DbName already exists, skipped."
}

# --- 5. Generate .env ---
$EnvFile = Join-Path $AppDir '.env'
$EnvExample = Join-Path $AppDir '.env.example'
if (-not (Test-Path $EnvFile)) {
    if (-not (Test-Path $EnvExample)) { throw "No .env.example at $EnvExample" }
    Copy-Item $EnvExample $EnvFile
    Log 'Created .env from .env.example'
}

function Set-EnvKey([string]$Key, [string]$Value) {
    $content = Get-Content $EnvFile -Raw
    $escaped = [Regex]::Escape($Key)
    $pattern = "(?m)^$escaped=.*$"
    $line    = "$Key=$Value"
    if ($content -match $pattern) {
        $content = [Regex]::Replace($content, $pattern, $line)
    } else {
        $content = $content.TrimEnd() + "`r`n$line`r`n"
    }
    Set-Content -Path $EnvFile -Value $content -NoNewline -Encoding ascii
}

Set-EnvKey 'APP_ENV'              'production'
Set-EnvKey 'APP_DEBUG'             'false'
Set-EnvKey 'APP_URL'              "http://localhost:$Port"
Set-EnvKey 'DB_CONNECTION'         'pgsql'
Set-EnvKey 'DB_HOST'               '127.0.0.1'
Set-EnvKey 'DB_PORT'               '5432'
Set-EnvKey 'DB_DATABASE'           $DbName
Set-EnvKey 'DB_USERNAME'           $DbUser
Set-EnvKey 'DB_PASSWORD'           $DbPass
Set-EnvKey 'THERMAL_PRINTER_NAME'  $PrinterQueue

# --- 6. Composer deps ---
if (Test-Path $Composer) {
    Log 'Running composer install…'
    Run $PhpExe @($Composer,'install','--no-dev','--optimize-autoloader','--no-interaction') $AppDir
} else {
    Log 'composer.phar not found in install root — skipping (assume vendor\ was shipped).'
}

# --- 7. APP_KEY ---
$envNow = Get-Content $EnvFile -Raw
if ($envNow -notmatch '(?m)^APP_KEY=base64:.+$') {
    Run $PhpExe @('artisan','key:generate','--force') $AppDir
}

# --- 8. Migrations + seed + printer + admin ---
Run $PhpExe @('artisan','migrate','--force')                            $AppDir
Run $PhpExe @('artisan','db:seed','--class=SettingSeeder','--force')    $AppDir
Run $PhpExe @('artisan','pos:set-printer',$PrinterQueue)                $AppDir
Run $PhpExe @('artisan','pos:create-admin',$AdminEmail,$AdminPassword)  $AppDir

# --- 9. Storage permissions ---
Log 'Granting Users modify on storage\ and bootstrap\cache\'
foreach ($sub in 'storage','bootstrap\cache') {
    $p = Join-Path $AppDir $sub
    if (Test-Path $p) {
        & icacls $p '/grant' 'Users:(OI)(CI)M' '/T' '/Q' | Out-Null
    }
}

# --- 10. Firewall ---
$ruleName = "Don David POS (TCP $Port)"
if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -LocalPort $Port -Protocol TCP -Action Allow | Out-Null
    Log "Firewall rule added: $ruleName"
}

# --- 11. Desktop shortcut ---
$icon = Join-Path $InstallRoot 'DonDavid.ico'
$startPs1 = Join-Path $ScriptsDir 'start.ps1'
$desktop = [Environment]::GetFolderPath('CommonDesktopDirectory')
$lnkPath = Join-Path $desktop 'Don David POS.lnk'

$wsh = New-Object -ComObject WScript.Shell
$lnk = $wsh.CreateShortcut($lnkPath)
$lnk.TargetPath       = 'powershell.exe'
$lnk.Arguments        = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$startPs1`""
$lnk.WorkingDirectory = $InstallRoot
if (Test-Path $icon) { $lnk.IconLocation = $icon }
$lnk.Description      = 'Arranca el sistema Don David POS'
$lnk.Save()
Log "Desktop shortcut created: $lnkPath"

# --- 12. Startup folder (optional) ---
if ($CreateStartupShortcut) {
    $startupDir = [Environment]::GetFolderPath('CommonStartup')
    $startupLnk = Join-Path $startupDir 'Don David POS.lnk'
    Copy-Item $lnkPath $startupLnk -Force
    Log "Auto-start shortcut installed: $startupLnk"
}

Log '==== install complete ===='
