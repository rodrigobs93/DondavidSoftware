# Carnicería Don David — Instalación en PC POS (Windows)

## Credenciales por defecto

| Usuario | Email | Contraseña | Rol |
|---------|-------|-----------|-----|
| Administrador | admin@dondavid.co | DonDavid2024! | Admin |
| Cajero | cajero@dondavid.co | Cajero2024! | Cashier |

**Cambiar contraseñas inmediatamente en producción.**

---

## Requisitos instalados

| Componente | Versión |
|-----------|---------|
| PHP | 8.2+ |
| Composer | 2.x |
| PostgreSQL | 16 |

Todos se instalaron vía WinGet. El ejecutable PHP está en:
```
C:\Users\<usuario>\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\
```

---

## Iniciar los servicios

### Opción A — Manual (desarrollo/pruebas)

Abre **dos terminales** (Git Bash o PowerShell) en la carpeta `app/`:

**Terminal 1 — Servidor web:**
```bash
cd "C:/Users/rodri/OneDrive/COLDEVS/donDavidSoftware/app"
php artisan serve --host=0.0.0.0 --port=8000
```

**Terminal 2 — Print worker:**
```bash
cd "C:/Users/rodri/OneDrive/COLDEVS/donDavidSoftware/app"
php artisan app:print-worker
```

Accede desde el PC: **http://localhost:8000**
Accede desde celular (misma WiFi): **http://192.168.1.100:8000** ← ajusta la IP

### Opción B — Servicios Windows con NSSM (producción)

Descarga NSSM desde https://nssm.cc/ y colócalo en `C:\nssm\nssm.exe`.

Abre una terminal como **Administrador** y ejecuta:

```batch
REM Servicio web
C:\nssm\nssm.exe install DonDavidWeb "C:\Users\rodri\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
C:\nssm\nssm.exe set DonDavidWeb AppParameters "artisan serve --host=0.0.0.0 --port=8000"
C:\nssm\nssm.exe set DonDavidWeb AppDirectory "C:\Users\rodri\OneDrive\COLDEVS\donDavidSoftware\app"
C:\nssm\nssm.exe set DonDavidWeb Start SERVICE_AUTO_START

REM Servicio print worker
C:\nssm\nssm.exe install DonDavidPrint "C:\Users\rodri\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
C:\nssm\nssm.exe set DonDavidPrint AppParameters "artisan app:print-worker"
C:\nssm\nssm.exe set DonDavidPrint AppDirectory "C:\Users\rodri\OneDrive\COLDEVS\donDavidSoftware\app"
C:\nssm\nssm.exe set DonDavidPrint Start SERVICE_AUTO_START

REM Iniciar servicios
C:\nssm\nssm.exe start DonDavidWeb
C:\nssm\nssm.exe start DonDavidPrint
```

Para detener: `C:\nssm\nssm.exe stop DonDavidWeb`

---

## Configurar IP estática (recomendado)

Para que la URL LAN no cambie cuando el router reinicia:

1. Abre **Panel de control → Redes → Adaptador de red**
2. Click derecho en el adaptador WiFi/Ethernet → Propiedades
3. **Protocolo TCP/IPv4** → Usar la dirección IP siguiente:
   - IP: `192.168.1.100` (o la que asignes)
   - Máscara: `255.255.255.0`
   - Puerta de enlace: `192.168.1.1` (IP de tu router)

4. Actualiza `APP_LAN_IP` en `.env` y en Configuración del sistema.

---

## Configurar la impresora térmica

1. Conecta la impresora USB y anota el puerto COM en **Device Manager**.
2. Edita `.env`: `THERMAL_PRINTER_PORT=COM3` (o el puerto que sea).
3. En el sistema: **Config → Puerto impresora** → guardar.
4. Reinicia el print worker.

**Si la impresión falla:** El worker intentará hasta 3 veces antes de marcar el job como FAILED. Los errores aparecen en el Dashboard.

---

## Backup manual

1. Accede a **Config → Exportar Backup SQL**.
2. Se descarga un archivo `dondavid_backup_YYYY-MM-DD_HHMMSS.sql`.
3. Si configuraste una ruta OneDrive, también se copia allí automáticamente.

Para restaurar:
```bash
psql -U don_david_user -d don_david -f backup_archivo.sql
```

---

## Firewall de Windows

Abre el puerto 8000 para acceso LAN:
```batch
netsh advfirewall firewall add rule name="DonDavid POS" dir=in action=allow protocol=TCP localport=8000
```

---

## Comandos útiles

```bash
# Correr migraciones de nuevo (si cambias el schema)
php artisan migrate

# Re-ejecutar seeders
php artisan db:seed --force

# Ver logs de la app
php artisan pail

# Ver jobs de impresión pendientes
php artisan tinker
>>> App\Models\PrintJob::where('status', 'QUEUED')->get()
```

---

## Estructura del proyecto

```
app/
├── app/
│   ├── Console/Commands/PrintWorker.php     ← Daemon de impresión
│   ├── Http/
│   │   ├── Controllers/                     ← Todos los controladores
│   │   └── Middleware/
│   │       ├── EnsureLanAccess.php          ← Restringe cajero a LAN
│   │       └── EnsureAdmin.php              ← Solo admins
│   ├── Models/                              ← Eloquent models
│   └── Services/
│       ├── SaleService.php                  ← Transacción de venta
│       └── EscPosTicketRenderer.php         ← ESC/POS thermal
├── database/
│   ├── migrations/                          ← 12 migraciones
│   └── seeders/                             ← Admin, GENERIC, productos, settings
└── resources/views/                         ← 11 pantallas Blade + Alpine.js
```
