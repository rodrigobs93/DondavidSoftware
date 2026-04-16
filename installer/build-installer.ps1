<#
.SYNOPSIS
  Build the Don David POS Windows installer.

.DESCRIPTION
  Steps:
    1. Downloads portable PHP 8.2 and Postgres 16 zips into installer\payload\
       (only if not already present).
    2. Copies the Laravel app from ..\app to payload\app and runs
       `composer install --no-dev` inside it.
    3. Copies composer.phar into payload\ (for future in-place upgrades).
    4. Invokes Inno Setup Compiler (ISCC) on DonDavid.iss to produce
       installer\output\DonDavidSetup-<version>.exe.

.PARAMETER ISCCPath
  Path to Inno Setup's ISCC.exe.

.NOTES
  Run on the dev machine with internet access, PHP 8.2 and Composer available
  (or bundled). The output .exe is 100% offline-installable.
#>

param(
    [string]$ISCCPath = 'C:\Program Files (x86)\Inno Setup 6\ISCC.exe',
    [switch]$SkipPhpPostgres,
    [switch]$SkipApp
)

$ErrorActionPreference = 'Stop'

$InstallerDir = $PSScriptRoot
$PayloadDir   = Join-Path $InstallerDir 'payload'
$OutputDir    = Join-Path $InstallerDir 'output'
$RepoRoot     = Split-Path $InstallerDir -Parent
$AppSrc       = Join-Path $RepoRoot 'app'

New-Item -ItemType Directory -Force -Path $PayloadDir,$OutputDir | Out-Null

function Write-Step([string]$m) { Write-Host ">>> $m" -ForegroundColor Cyan }

# ---- Pinned download URLs ----
# Portable PHP 8.2 Windows x64 non-thread-safe (for CLI use)
$PhpUrl = 'https://windows.php.net/downloads/releases/archives/php-8.2.27-nts-Win32-vs16-x64.zip'
# EnterpriseDB portable Postgres 16
$PgUrl  = 'https://get.enterprisedb.com/postgresql/postgresql-16.4-1-windows-x64-binaries.zip'
# Composer
$ComposerUrl = 'https://getcomposer.org/composer-stable.phar'

# ---- 1. Payload: PHP + Postgres + Composer ----
if (-not $SkipPhpPostgres) {
    $PhpZip = Join-Path $PayloadDir 'php.zip'
    $PhpDir = Join-Path $PayloadDir 'php'
    if (-not (Test-Path (Join-Path $PhpDir 'php.exe'))) {
        Write-Step 'Downloading portable PHP 8.2...'
        if (-not (Test-Path $PhpZip)) { Invoke-WebRequest -Uri $PhpUrl -OutFile $PhpZip }
        Write-Step 'Extracting PHP...'
        Remove-Item $PhpDir -Recurse -Force -ErrorAction SilentlyContinue
        Expand-Archive -Path $PhpZip -DestinationPath $PhpDir -Force

        # Enable required extensions in php.ini
        $iniSrc = Join-Path $PhpDir 'php.ini-production'
        $iniDst = Join-Path $PhpDir 'php.ini'
        Copy-Item $iniSrc $iniDst -Force
        $ini = Get-Content $iniDst -Raw
        $ini = $ini -replace ';extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'
        foreach ($ext in 'pdo_pgsql','pgsql','mbstring','openssl','fileinfo','curl','zip','intl','gd','sockets','bcmath') {
            $ini = $ini -replace ";extension=$ext", "extension=$ext"
        }
        Set-Content -Path $iniDst -Value $ini -NoNewline
    }

    $PgZip = Join-Path $PayloadDir 'pgsql.zip'
    $PgDir = Join-Path $PayloadDir 'pgsql'
    if (-not (Test-Path (Join-Path $PgDir 'bin\postgres.exe'))) {
        Write-Step 'Downloading portable Postgres 16 (large, ~350 MB)...'
        if (-not (Test-Path $PgZip)) { Invoke-WebRequest -Uri $PgUrl -OutFile $PgZip }
        Write-Step 'Extracting Postgres...'
        $tmp = Join-Path $PayloadDir 'pgsql_tmp'
        Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
        Expand-Archive -Path $PgZip -DestinationPath $tmp -Force
        # EDB zip extracts to pgsql\ — flatten
        if (Test-Path (Join-Path $tmp 'pgsql')) {
            Remove-Item $PgDir -Recurse -Force -ErrorAction SilentlyContinue
            Move-Item (Join-Path $tmp 'pgsql') $PgDir
        }
        Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
    }

    $ComposerPhar = Join-Path $PayloadDir 'composer.phar'
    if (-not (Test-Path $ComposerPhar)) {
        Write-Step 'Downloading composer.phar...'
        Invoke-WebRequest -Uri $ComposerUrl -OutFile $ComposerPhar
    }
}

# ---- 2. Payload: app ----
if (-not $SkipApp) {
    $AppDst = Join-Path $PayloadDir 'app'
    Write-Step 'Copying Laravel app into payload\app...'
    Remove-Item $AppDst -Recurse -Force -ErrorAction SilentlyContinue
    Copy-Item $AppSrc $AppDst -Recurse -Force
    # Strip dev-only files
    foreach ($trash in '.env','.phpunit.result.cache','node_modules','tests\Feature\ExampleTest.php') {
        $p = Join-Path $AppDst $trash
        if (Test-Path $p) { Remove-Item $p -Recurse -Force }
    }

    Write-Step 'Running composer install --no-dev inside payload\app...'
    $php = Join-Path $PayloadDir 'php\php.exe'
    $composerPhar = Join-Path $PayloadDir 'composer.phar'
    & $php $composerPhar install --no-dev --optimize-autoloader --no-interaction --working-dir=$AppDst
    if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }
}

# ---- 3. Icon ----
$IconSrc = Join-Path $RepoRoot 'installer\assets\DonDavid.ico'
$IconDst = Join-Path $PayloadDir 'DonDavid.ico'
if (Test-Path $IconSrc) {
    Copy-Item $IconSrc $IconDst -Force
} elseif (-not (Test-Path $IconDst)) {
    Write-Host 'No icon found at installer\assets\DonDavid.ico — using Windows default.' -ForegroundColor Yellow
    # Create a 1x1 placeholder so ISCC does not fail
    $stubBytes = [byte[]](0,0,1,0,1,0,1,1,0,0,1,0,32,0,40,0,0,0,22,0,0,0,40,0,0,0,1,0,0,0,2,0,0,0,1,0,32,0,0,0,0,0,4,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0)
    [System.IO.File]::WriteAllBytes($IconDst, $stubBytes)
}

# ---- 4. Run Inno Setup Compiler ----
if (-not (Test-Path $ISCCPath)) {
    throw "Inno Setup Compiler not found at '$ISCCPath'. Install Inno Setup 6 or pass -ISCCPath."
}
Write-Step 'Compiling installer with ISCC...'
& $ISCCPath (Join-Path $InstallerDir 'DonDavid.iss')
if ($LASTEXITCODE -ne 0) { throw 'ISCC failed' }

Write-Host ''
Write-Host 'Installer built successfully:' -ForegroundColor Green
Get-ChildItem $OutputDir -Filter '*.exe' | Format-Table Name,Length,LastWriteTime
