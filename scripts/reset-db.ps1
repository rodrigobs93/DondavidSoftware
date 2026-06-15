<#
.SYNOPSIS
  Reset the database to a clean, EMPTY state (LOCAL / TEST only).

.DESCRIPTION
  Drops all tables, re-runs every migration, then seeds ONLY the minimal records
  the app needs to operate (settings keys, generic customer, admin + cashier
  logins). No demo/sample data (the sample product catalog is NOT inserted).

  Equivalent to:
    php artisan migrate:fresh --force
    php artisan db:seed --class=MinimalSeeder --force

  SAFETY: refuses to run unless the application environment is 'local' or
  'testing', AND requires you to type RESET to confirm (skip with -Yes).

.PARAMETER Yes
  Skip the interactive confirmation prompt (the environment guard still applies).

.PARAMETER PhpExe
  Full path to php.exe. Auto-detected if omitted.

.EXAMPLE
  ./scripts/reset-db.ps1
  ./scripts/reset-db.ps1 -Yes
#>
param(
    [switch]$Yes,
    [string]$PhpExe
)

$ErrorActionPreference = 'Stop'

# ── Locate the app + php ──────────────────────────────────────────────────────
$Root    = Split-Path $PSScriptRoot -Parent
$AppDir  = Join-Path $Root 'app'
$Artisan = Join-Path $AppDir 'artisan'

if (-not (Test-Path $Artisan)) {
    Write-Host "No se encontro artisan en $AppDir" -ForegroundColor Red
    exit 1
}

function Resolve-Php {
    param([string]$Override)
    $candidates = @()
    if ($Override)      { $candidates += $Override }
    if ($env:PHP_BIN)   { $candidates += (Join-Path $env:PHP_BIN 'php.exe'); $candidates += $env:PHP_BIN }
    $candidates += (Join-Path (Split-Path $PSScriptRoot -Parent) 'php\php.exe')   # prod-like layout
    # WinGet install (dev machine)
    $winget = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.*\php.exe" -ErrorAction SilentlyContinue |
              Select-Object -First 1
    if ($winget) { $candidates += $winget.FullName }
    $onPath = (Get-Command php -ErrorAction SilentlyContinue).Source
    if ($onPath) { $candidates += $onPath }

    foreach ($c in $candidates) {
        if ($c -and (Test-Path $c)) { return (Resolve-Path $c).Path }
    }
    return $null
}

$Php = Resolve-Php -Override $PhpExe
if (-not $Php) {
    Write-Host "No se encontro php.exe. Use -PhpExe 'C:\ruta\php.exe' o defina `$env:PHP_BIN." -ForegroundColor Red
    exit 1
}

# ── Authoritative environment + DB name (respects real env precedence) ────────
$probe = & $Php $Artisan tinker --execute="echo '@@'.app()->environment().'@@'.config('database.connections.'.config('database.default').'.database').'@@';" 2>&1 | Out-String
$m = [regex]::Match($probe, '@@(.*?)@@(.*?)@@')
if (-not $m.Success) {
    Write-Host "No se pudo determinar el entorno de la aplicacion." -ForegroundColor Red
    Write-Host $probe
    exit 1
}
$AppEnv = $m.Groups[1].Value.Trim()
$DbName = $m.Groups[2].Value.Trim()

# ── Safety guard: never in production ─────────────────────────────────────────
$allowed = @('local', 'testing')
if ($allowed -notcontains $AppEnv) {
    Write-Host "BLOQUEADO: APP_ENV='$AppEnv'. Este script solo corre en 'local' o 'testing'." -ForegroundColor Red
    exit 2
}

Write-Host ""
Write-Host "  Entorno : $AppEnv" -ForegroundColor Yellow
Write-Host "  Base    : $DbName" -ForegroundColor Yellow
Write-Host "  Accion  : DROP de TODAS las tablas + migraciones + seed minimo" -ForegroundColor Yellow
Write-Host ""

# ── Explicit confirmation ─────────────────────────────────────────────────────
if (-not $Yes) {
    $answer = Read-Host "Esto BORRA todos los datos de '$DbName'. Escriba RESET para continuar"
    if ($answer -ne 'RESET') {
        Write-Host "Cancelado." -ForegroundColor Cyan
        exit 0
    }
}

# ── Run ───────────────────────────────────────────────────────────────────────
Write-Host "`n→ migrate:fresh ..." -ForegroundColor Cyan
& $Php $Artisan migrate:fresh --force
if ($LASTEXITCODE -ne 0) { throw "migrate:fresh fallo (exit $LASTEXITCODE)" }

Write-Host "`n→ seed minimo (MinimalSeeder) ..." -ForegroundColor Cyan
& $Php $Artisan db:seed --class=MinimalSeeder --force
if ($LASTEXITCODE -ne 0) { throw "db:seed fallo (exit $LASTEXITCODE)" }

# ── Summary (confirm minimal state) ───────────────────────────────────────────
Write-Host "`n→ Verificando estado..." -ForegroundColor Cyan
$verify = "echo 'users='.App\Models\User::count().' customers='.App\Models\Customer::count().' products='.App\Models\Product::count().' settings='.App\Models\Setting::count().' invoices='.App\Models\Invoice::count().' suppliers='.App\Models\Supplier::count().' supplier_invoices='.App\Models\SupplierInvoice::count();"
$summary = & $Php $Artisan tinker "--execute=$verify" 2>&1 |
           Select-String -Pattern 'users=\d' | Select-Object -First 1

Write-Host "`nOK - Base reiniciada." -ForegroundColor Green
if ($summary) { Write-Host "  $($summary.ToString().Trim())" -ForegroundColor Green }
Write-Host "  (esperado: products=0, invoices=0, supplier*=0; users/customers/settings = minimo)" -ForegroundColor DarkGray
