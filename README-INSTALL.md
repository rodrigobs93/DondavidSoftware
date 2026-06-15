# Mi Negocio POS — Instalación en PC POS (Windows)

## Credenciales por defecto

| Usuario | Email | Contraseña | Rol |
|---------|-------|-----------|-----|
| Administrador | admin@minegocio.local | Admin1234! | Admin |
| Cajero | cajero@minegocio.local | Cajero2024! | Cashier |

**Cambiar contraseñas inmediatamente en producción.**

---

## ¿Olvidaste tu contraseña?

### Opción A — Desde el navegador (recomendado)

1. En la pantalla de inicio de sesión, click **¿Olvidaste tu contraseña?**
2. Ingresa la **contraseña maestra**, tu email de administrador, y la nueva contraseña.
3. La contraseña maestra de tu instalación está en:
   ```
   C:\MiPOS\run\master_reset.txt
   ```
   También aparece en `C:\MiPOS\app\.env` como `MASTER_RESET_PASSWORD=`.
4. ¿Quieres cambiarla? Edita `.env`, guarda, y reinicia el servidor (`stop.ps1` → `start.ps1`).

### Opción B — Script en el PC del POS (fallback)

Si no tienes acceso al navegador, ejecuta:

```powershell
C:\MiPOS\scripts\reset-admin.ps1
```

Te pedirá el email (por defecto `admin@minegocio.local`) y la nueva contraseña. Internamente corre `php artisan pos:create-admin`. Si tu instalación está en otra ruta, ajusta `$env:MIPOS_ROOT` antes de ejecutar el script.

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
C:\nssm\nssm.exe install MiPOSWeb "C:\Users\rodri\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
C:\nssm\nssm.exe set MiPOSWeb AppParameters "artisan serve --host=0.0.0.0 --port=8000"
C:\nssm\nssm.exe set MiPOSWeb AppDirectory "C:\Users\rodri\OneDrive\COLDEVS\donDavidSoftware\app"
C:\nssm\nssm.exe set MiPOSWeb Start SERVICE_AUTO_START

REM Servicio print worker
C:\nssm\nssm.exe install MiPOSPrint "C:\Users\rodri\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
C:\nssm\nssm.exe set MiPOSPrint AppParameters "artisan app:print-worker"
C:\nssm\nssm.exe set MiPOSPrint AppDirectory "C:\Users\rodri\OneDrive\COLDEVS\donDavidSoftware\app"
C:\nssm\nssm.exe set MiPOSPrint Start SERVICE_AUTO_START

REM Iniciar servicios
C:\nssm\nssm.exe start MiPOSWeb
C:\nssm\nssm.exe start MiPOSPrint
```

Para detener: `C:\nssm\nssm.exe stop MiPOSWeb`

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

## Módulos opcionales

- **Proveedores / Cuentas por Pagar:** viene **apagado** por defecto. Para activarlo:
  **Config → Módulos → Habilitar módulo de Proveedores** y guardar. Aparecerá
  "Proveedores" en el menú (solo administradores). Permite registrar facturas de
  proveedor (ítems por KG o unidad), pagos ligados a una factura o pagos al
  proveedor que se reparten automáticamente (FIFO) a las facturas más antiguas, e
  imprimir el estado de cuenta. Mientras esté apagado, el menú se oculta y las URLs
  directas devuelven 403.

---

## Backup manual

1. Accede a **Config → Exportar Backup SQL**.
2. Se descarga un archivo `mipos_backup_YYYY-MM-DD_HHMMSS.sql`.
3. Si configuraste una ruta OneDrive, también se copia allí automáticamente.

Para restaurar:
```bash
psql -U mi_pos_user -d mi_pos -f backup_archivo.sql
```

---

## Firewall de Windows

Abre el puerto 8000 para acceso LAN:
```batch
netsh advfirewall firewall add rule name="Mi Negocio POS" dir=in action=allow protocol=TCP localport=8000
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

## Resetear la base de datos a estado limpio (solo LOCAL/TEST)

Para dejar la base **vacía** (estado limpio) durante pruebas, usa el script:

```powershell
# Desde la raíz del repositorio
./scripts/reset-db.ps1          # pide confirmación (escribir RESET)
./scripts/reset-db.ps1 -Yes     # sin confirmación (para automatización)
```

Qué hace:

1. `php artisan migrate:fresh --force` — elimina **todas** las tablas y vuelve a
   correr todas las migraciones.
2. `php artisan db:seed --class=MinimalSeeder --force` — inserta **solo** lo
   mínimo necesario para operar:
   - llaves de configuración (`settings`),
   - el cliente **GENÉRICO** requerido,
   - los usuarios `admin@minegocio.local` y `cajero@minegocio.local`.

**No** inserta datos de demo/muestra (el catálogo de productos de ejemplo del
`DatabaseSeeder` **no** se crea). Al terminar imprime un resumen:
`products=0, invoices=0, supplier*=0`.

Seguridad:

- Se **bloquea** si `APP_ENV` no es `local` ni `testing` (nunca corre en
  producción) y exige escribir `RESET` para confirmar.
- `php.exe` se detecta automáticamente; si hace falta: `-PhpExe 'C:\ruta\php.exe'`
  o define `$env:PHP_BIN`.

> El instalador de producción usa `DatabaseSeeder` (con catálogo de muestra). Este
> reset es **exclusivo para desarrollo/pruebas**.

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
│   └── seeders/                             ← Admin, productos, settings
└── resources/views/                         ← 11 pantallas Blade + Alpine.js
```
