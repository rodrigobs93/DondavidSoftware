<#
.SYNOPSIS
  Reset (or create) the admin password for Mi Negocio POS.

.DESCRIPTION
  Wraps `php artisan pos:create-admin` so the shop owner can recover
  access without typing the full PHP / artisan path. Safe to re-run.

.PARAMETER Email
  Admin email. Defaults to admin@minegocio.local if omitted (prompted).

.PARAMETER Password
  New password (plaintext). If omitted, the script prompts securely.
#>
param(
    [string]$Email,
    [string]$Password
)

$ErrorActionPreference = 'Stop'

$Root    = if ($env:MIPOS_ROOT) { $env:MIPOS_ROOT } else { 'C:\MiPOS' }
$AppDir  = Join-Path $Root 'app'
$PhpExe  = Join-Path $Root 'php\php.exe'
$Artisan = Join-Path $AppDir 'artisan'

if (-not (Test-Path $PhpExe) -or -not (Test-Path $Artisan)) {
    [void][System.Reflection.Assembly]::LoadWithPartialName('System.Windows.Forms')
    [System.Windows.Forms.MessageBox]::Show(
        "Mi Negocio POS no esta instalado en $Root.`nEjecuta DonDavidSetup.exe primero.",
        'Mi Negocio POS', 'OK', 'Error'
    ) | Out-Null
    exit 1
}

$interactive = $false

if (-not $Email) {
    $interactive = $true
    $Email = Read-Host 'Email del administrador (Enter = admin@minegocio.local)'
    if ([string]::IsNullOrWhiteSpace($Email)) { $Email = 'admin@minegocio.local' }
}

if (-not $Password) {
    $interactive = $true
    $sec      = Read-Host 'Nueva contrasena' -AsSecureString
    $Password = [Net.NetworkCredential]::new('', $sec).Password
}

if ($Password.Length -lt 6) {
    Write-Host 'La contrasena debe tener al menos 6 caracteres.' -ForegroundColor Red
    if ($interactive) { Read-Host 'Presiona Enter para cerrar' }
    exit 2
}

try {
    & $PhpExe $Artisan 'pos:create-admin' $Email $Password
    if ($LASTEXITCODE -ne 0) { throw "artisan exit $LASTEXITCODE" }
    Write-Host "`nOK - Admin $Email listo. Ya puedes iniciar sesion." -ForegroundColor Green
} catch {
    Write-Host "`nError: $_" -ForegroundColor Red
    if ($interactive) { Read-Host 'Presiona Enter para cerrar' }
    exit 3
}

if ($interactive) { Read-Host 'Presiona Enter para cerrar' }
