<#
.SYNOPSIS
  Inicia Mi Negocio POS: base de datos, servidor web, y abre el navegador.

.DESCRIPTION
  1. Verifica (e intenta iniciar) el servicio de PostgreSQL.
  2. Lanza "php artisan serve" en una ventana aparte, escuchando en todas las
     interfaces (0.0.0.0) para permitir conexiones externas via Tailscale/LAN.
  3. Espera a que el servidor responda en el puerto configurado.
  4. Abre http://127.0.0.1:8000 en el navegador por defecto.

.PARAMETER Port
  Puerto del servidor web. Por defecto 8000.

.EXAMPLE
  ./scripts/start-mipos.ps1
#>
param(
    [int]$Port = 8000,
    [string]$PgServiceName = 'postgresql-x64-16'
)

$ErrorActionPreference = 'Stop'

$Root      = Split-Path $PSScriptRoot -Parent
$AppDir    = Join-Path $Root 'app'
$Artisan   = Join-Path $AppDir 'artisan'
$BindHost  = '0.0.0.0'
$Url       = "http://127.0.0.1:$Port"

if (-not (Test-Path $Artisan)) {
    Write-Host "No se encontro artisan en $AppDir" -ForegroundColor Red
    exit 1
}

# ── 1. Base de datos ──────────────────────────────────────────────────────────
$pg = Get-Service -Name $PgServiceName -ErrorAction SilentlyContinue
if ($null -eq $pg) {
    Write-Host "Aviso: no se encontro el servicio '$PgServiceName'. Verifica que PostgreSQL este instalado." -ForegroundColor Yellow
} elseif ($pg.Status -ne 'Running') {
    Write-Host "Iniciando PostgreSQL ($PgServiceName)..."
    try {
        Start-Service -Name $PgServiceName
        Write-Host "PostgreSQL iniciado." -ForegroundColor Green
    } catch {
        Write-Host "No se pudo iniciar PostgreSQL automaticamente (requiere permisos de administrador)." -ForegroundColor Yellow
        Write-Host "Abre 'Servicios' e inicia '$PgServiceName' manualmente, o ejecuta este script como Administrador." -ForegroundColor Yellow
    }
} else {
    Write-Host "PostgreSQL ya esta corriendo." -ForegroundColor Green
}

# ── 2. Servidor web (en ventana aparte, para poder ver sus logs) ─────────────
Write-Host "Iniciando servidor web en 0.0.0.0:$Port (acepta conexiones LAN/Tailscale) ..."
Start-Process -FilePath 'php' `
    -ArgumentList "artisan serve --host=$BindHost --port=$Port" `
    -WorkingDirectory $AppDir `
    -WindowStyle Normal

# ── 3. Esperar a que el servidor responda ────────────────────────────────────
$maxWaitSeconds = 20
$elapsed = 0
$ready = $false
while ($elapsed -lt $maxWaitSeconds) {
    try {
        $resp = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 2
        if ($resp.StatusCode -ge 200) { $ready = $true; break }
    } catch {
        # todavia no responde, seguir esperando
    }
    Start-Sleep -Seconds 1
    $elapsed++
}

# ── 4. Abrir navegador ────────────────────────────────────────────────────────
if ($ready) {
    Write-Host "Servidor listo. Abriendo $Url ..." -ForegroundColor Green
} else {
    Write-Host "El servidor no respondio en $maxWaitSeconds s, abriendo de todas formas..." -ForegroundColor Yellow
}
Start-Process $Url

# ── 5. Mostrar IP de Tailscale (si esta disponible) para acceso externo ─────
$tailscaleIp = $null
try {
    $tailscaleIp = (& tailscale ip -4 2>$null | Select-Object -First 1)
} catch {
    # tailscale CLI no disponible en PATH, se omite
}
if ($tailscaleIp) {
    Write-Host "Acceso via Tailscale: http://$tailscaleIp`:$Port" -ForegroundColor Cyan
}
