# Don David Software — Historic Notes

> Carnicería Don David · POS + Facturación Local-First · Bogotá, Colombia
> Documento creado: 2026-02-24

---

## Lo que se ha hecho

### Infraestructura / entorno (Sprint 0 parcial)

- **PHP 8.2** instalado vía WinGet (sin acceso admin) en:
  `C:\Users\rodri\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\`
- `php.ini` configurado manualmente (copiado de `php.ini-development`):
  - `extension_dir` apuntando a la ruta absoluta Windows de `ext\`
  - Extensiones habilitadas: `openssl`, `pdo_pgsql`, `pgsql`, `mbstring`, `curl`, `zip`, `bcmath`, `intl`, `fileinfo`, `gd`, `sodium`
- **Composer** instalado descargándolo vía curl (WinGet no tiene paquete).
- **PostgreSQL 16** instalado vía WinGet.
- Base de datos creada: `don_david`, usuario: `don_david_user`, password: `don_david_pass`.
- Scripts de shell escritos en disco para evitar problemas de rutas con espacios en bash:
  - `setup.sh` — creó el proyecto Laravel
  - `setup_db.sh` — creó DB y usuario en PostgreSQL
  - `run_migrate.sh` — corre migraciones y seeders
  - `run_test.sh` — verifica routes, key, extensiones

### Proyecto Laravel (M1–M6 completados)

- Proyecto creado en `donDavidSoftware/app/` con `composer create-project laravel/laravel`.
- `.env` configurado para PostgreSQL, zona horaria Bogotá, variables custom.
- `.env.example` documentado con todas las variables necesarias.
- `bootstrap/app.php` registra aliases de middleware `lan` y `admin`.

#### Migraciones (12 tablas)

| Tabla | Notas clave |
|-------|------------|
| `users` | role CHECK (admin/cashier), active flag |
| `customers` | is_generic con unique index parcial, requires_fe |
| `products` | sale_unit (KG/UNIT), price_updated_by |
| `customer_product_prices` | placeholder Fase 2, sin UI |
| `invoices` | SEQUENCE para consecutivo, balance almacenado, voided flag (Fase 2) |
| `invoice_items` | snapshot de nombre y precio, timestamps = solo created_at |
| `payments` | method CHECK (CASH/CARD/NEQUI/DAVIPLATA/BREB) |
| `print_jobs` | payload JSONB completo, status QUEUED/PRINTING/PRINTED/FAILED |
| `settings` | clave-valor para config del negocio |
| `sessions` | driver database para sesiones Laravel |
| `cache` | driver database para caché Laravel |
| `jobs` | queue database (no usada en Fase 1, estructura presente) |

**Todas las migraciones corrieron exitosamente.**

#### Seeders

- `UserSeeder` — admin@dondavid.co / DonDavid2024! (admin) · cajero@dondavid.co / Cajero2024! (cashier)
- `CustomerSeeder` — cliente GENERIC (is_generic=true, requerido, no borrable)
- `ProductSeeder` — 10 productos de muestra (costilla res, lomo, chorizo, morcilla, etc.)
- `SettingSeeder` — 8 settings iniciales (nombre, dirección, NIT, teléfono, footer tiquete, lan_ip, backup_path, puerto impresora)

**Todos los seeders corrieron exitosamente.**

#### Modelos Eloquent (8)

`User`, `Customer`, `Product`, `Invoice`, `InvoiceItem`, `Payment`, `PrintJob`, `Setting`

Helpers notables:
- `Invoice::getFeLabelAttribute()` — devuelve `'FE: NO'` / `'FE: PENDIENTE'` / `'FE: EMITIDA - {ref}'`
- `InvoiceItem::getFormattedQuantityAttribute()` — `'1.250 kg'` o `'4 und'`
- `Payment::$methods` — mapa código → label español
- `Setting::get(key, default)` / `Setting::set(key, value)` — helpers estáticos

#### Middleware

- `EnsureLanAccess` — bloquea al cajero si su IP no es privada (usa `FILTER_VALIDATE_IP` con `FILTER_FLAG_NO_PRIV_RANGE`)
- `EnsureAdmin` — abort 403 si el usuario no es admin

#### Servicios

- `SaleService` — transacción atómica: consecutivo → invoice → items → payments → print_job → commit
- `EscPosTicketRenderer` — renderiza bytes ESC/POS desde payload JSONB; maneja alineación, negritas, corte de papel

#### Controladores (10)

`LoginController`, `DashboardController`, `ProductController`, `CustomerController`,
`SaleController`, `InvoiceController`, `CarteraController`, `FePendingController`,
`ReportController`, `BackupController`

#### Rutas (31)

Tres grupos:
- **Guest:** `/login` GET/POST, `/logout` POST
- **Auth + LAN:** Dashboard, ventas, facturas, cartera, FE pendiente, búsqueda JSON
- **Admin:** Productos, clientes, reportes, backups/settings

#### Vistas Blade (11 pantallas + layout)

| # | Vista | Descripción |
|---|-------|-------------|
| — | `layouts/app` | Nav, flash messages, Tailwind CDN, Alpine CDN |
| 1 | `auth/login` | Login centrado |
| 2 | `dashboard` | Stats del día, URL LAN, shortcuts |
| 3 | `sales/create` | Nueva venta (pantalla más compleja) |
| 4 | `invoices/index` | Lista de facturas con filtros |
| 5 | `invoices/show` | Detalle, abono, FE mark, reprint |
| 6 | `cartera/index` | Cartera con abono inline |
| 7 | `fe-pending/index` | Cola de FE pendiente |
| 8 | `products/index` | Precios con edición inline |
| 9 | `customers/index` | Lista clientes (GENERIC protegido) |
| 10 | `customers/create` + `edit` | CRUD clientes |
| 11 | `reports/payments` | Pagos por método en rango de fechas |
| 12 | `backups/index` | Config negocio + exportar backup |

`sales/create` tiene el componente Alpine.js más complejo:
- Autocomplete de clientes y productos (fetch JSON)
- Toggle FE con validación (bloquea GENERIC)
- Split payments con guarda de sobrepago
- Totales reactivos (subtotal, domicilio, saldo)

#### Comando Artisan: Print Worker

`app:print-worker` — loop infinito que:
1. Al arrancar: resetea jobs stuck en `PRINTING` → `QUEUED`
2. Toma el primer job `QUEUED` con `lockForUpdate()`
3. Renderiza ESC/POS desde el payload JSONB
4. Escribe bytes al puerto COM (`fopen($port, 'wb')`)
5. Marca `PRINTED` o vuelve a `QUEUED`; tras 3 fallos → `FAILED`

#### Documentación

- `README-INSTALL.md` — guía de instalación Windows con credenciales por defecto, NSSM, IP estática, firewall, backup/restore

---

## Decisiones técnicas

| Decisión | Alternativa descartada | Razón |
|----------|----------------------|-------|
| WinGet para instalar PHP/PostgreSQL | Chocolatey | No hay acceso de administrador |
| Scripts `.sh` para laravel setup | Inline bash multi-línea | Las rutas con espacios (`OneDrive/COLDEVS`) rompían `export` en bash |
| PostgreSQL SEQUENCE para consecutivo | Auto-increment Laravel | SEQUENCE es atómico bajo concurrencia; genera el int bruto que se formatea en PHP |
| `bcmath` para aritmética monetaria | Float/double PHP | Evita errores de punto flotante en totales y balances |
| Tailwind + Alpine.js via CDN | Vite build step | Sin build step en MVP; máxima simplicidad para un POS local |
| `balance` campo almacenado en `invoices` | Calculado en consulta | Simplifica queries de cartera y suma por índice parcial |
| Payload JSONB completo en `print_jobs` | Queries al imprimir | El daemon nunca necesita consultas adicionales; funciona aunque los datos cambien |
| `PRINT_JOB_SOURCE=database` en `.env` | Hardcoded | El switch prepara la migración a Fase 3 (cloud API) sin cambiar código |
| `voided` flag en `invoices` desde día 1 | Agregar en Fase 2 | El schema nunca cambia una vez en producción; campo presente, sin UI por ahora |
| `customer_product_prices` tabla vacía Fase 1 | Omitirla | Migrar en producción es costoso; mejor crearla vacía ahora |

---

## Problemas pendientes

### Crítico (bloquea MVP real)

| # | Problema | Detalle |
|---|---------|---------|
| P1 | ~~**`mb_str_pad()` no existe en PHP 8.2**~~ | **RESUELTO** — Reemplazado con `mb_strlen` + `str_repeat` en `pad()` y `padL()`. Commit `be0411a`. |
| P2 | **Impresora térmica no probada** | No se ha ejecutado el Sprint 0 físico: enviar bytes ESC/POS al puerto COM del PC POS real e imprimir un tiquete de prueba. Sin este paso no se sabe si el driver COM funciona desde PHP. |

### Importante

| # | Problema | Detalle |
|---|---------|---------|
| P3 | **PHP no está en el PATH del sistema** | Solo accesible vía ruta completa o scripts. Para usarlo desde terminal directo hay que añadir la carpeta WinGet al PATH de usuario en Variables de entorno de Windows. |
| P4 | **NSSM no instalado** | Los servicios Windows `DonDavidWeb` y `DonDavidPrint` no están registrados. La app solo corre manualmente con `php artisan serve`. |
| P5 | **Puerto 8000 no abierto en Firewall Windows** | Acceso LAN desde celular/otros equipos bloqueado hasta ejecutar la regla `netsh`. |
| P6 | **IP estática no asignada** | La URL LAN puede cambiar cuando el router reinicia. |

### Menor

| # | Problema | Detalle |
|---|---------|---------|
| P7 | **Backup: `pg_dump` path en Windows** | `BackupController` ejecuta `pg_dump`; en Windows la ruta de PostgreSQL 16 (`C:\Program Files\PostgreSQL\16\bin\pg_dump.exe`) debe estar en PATH o especificarse en el código. |
| P8 | **APP_KEY no persistida** | El `.env` original fue generado durante `composer create-project`; verificar que la key esté presente (`php artisan key:show`). |
| P9 | **Zona horaria `America/Bogota`** | `config/app.php` lee `APP_TIMEZONE` — presente en `.env` — pero no se ha verificado en runtime que los timestamps `invoice_date` usen la zona correcta. |

---

## Próximos pasos

### Inmediato (antes de primera prueba real)

1. ~~**Corregir `mb_str_pad()` en `EscPosTicketRenderer.php`**~~ **HECHO** — commit `be0411a`.

2. **Sprint 0 — Test de impresión físico**
   - Conectar la impresora USB
   - Anotar puerto COM en Device Manager
   - Actualizar `.env`: `THERMAL_PRINTER_PORT=COM3` (o el que sea)
   - Ejecutar `php artisan app:print-worker` y crear una venta de prueba
   - Verificar que el tiquete sale correctamente

3. **Añadir PHP al PATH de usuario**
   En PowerShell:
   ```powershell
   $phpPath = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
   [Environment]::SetEnvironmentVariable("PATH", $env:PATH + ";$phpPath", "User")
   ```

4. **Abrir puerto 8000 en Firewall**
   ```batch
   netsh advfirewall firewall add rule name="DonDavid POS" dir=in action=allow protocol=TCP localport=8000
   ```

5. **Asignar IP estática** al PC POS (Panel de control → Adaptador de red → TCP/IPv4).
   Luego actualizar `settings.lan_ip` en Config del sistema dentro de la app.

### Después del Sprint 0 exitoso

6. **Instalar NSSM** y registrar servicios Windows:
   - `DonDavidWeb` → `php artisan serve --host=0.0.0.0 --port=8000`
   - `DonDavidPrint` → `php artisan app:print-worker`
   (Ver comandos exactos en `README-INSTALL.md`)

7. **QA manual completo** (checklist del plan):
   - Login cajero desde IP externa → debe dar 403
   - Venta con producto KG: `1.250 kg` → `line_total` correcto
   - Split payment CASH + NEQUI exacto al total → status `PAID`
   - Split payment menor al total → `PARTIAL`, aparece en cartera
   - Overpago → botón Finalizar deshabilitado
   - Consecutivo `0000001`, `0000002`... sin duplicados
   - Abono reduce balance; al llegar a 0 → `PAID`, sale de cartera
   - Reprint → nuevo print_job creado → tiquete impreso
   - Backup SQL: descarga correcta, nombre `dondavid_backup_YYYY-MM-DD_HHMMSS.sql`

8. **Verificar backup path** con ruta OneDrive real configurada en Settings.

---

## Sesión 2026-02-27 — Módulo de Categorías de Productos

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| *(pendiente)* | feat(categories): add product categories module with inline editing |

---

### feat: Categorías de productos

Se implementó un módulo completo de categorías para organizar el catálogo de productos.

#### Migraciones nuevas

| Migración | Descripción |
|-----------|-------------|
| `2026_02_27_000002_create_product_categories_table.php` | Crea tabla `product_categories` (`id`, `name`, `active`, timestamps). Índice único case-insensitive en `lower(name)` via raw SQL PostgreSQL. |
| `2026_02_27_000003_add_category_id_to_products_table.php` | Añade FK `category_id` nullable a `products`; `onDelete('set null')` para preservar productos al borrar categoría. |

#### Modelo nuevo

- **`ProductCategory`** — `$fillable = ['name', 'active']`; relación `products()` hasMany.

#### Modelo modificado

- **`Product`** — añadida relación `category()` belongsTo `ProductCategory`.

#### Controlador nuevo

- **`CategoryController`** — 5 métodos:
  - `index()` — lista con `withCount('products')`
  - `store()` — valida `name` único (Rule::unique)
  - `update()` — edición inline vía JSON (nombre + active)
  - `toggleActive()` — activa/desactiva
  - `destroy()` — elimina categoría; productos quedan con `category_id = null`

#### Controlador modificado

- **`ProductController`**:
  - `index()` — filtra por `category_id`; eager-load `category`; incluye `category_id` y `category_name` en respuesta JSON
  - `updateCategory()` — nuevo método; actualiza `category_id` de un producto vía AJAX; retorna `category_id` y `category_name`

#### Rutas nuevas (6)

```
GET    /categories                     categories.index
POST   /categories                     categories.store
POST   /categories/{category}          categories.update
POST   /categories/{category}/toggle   categories.toggle
DELETE /categories/{category}          categories.destroy
POST   /products/{product}/category    products.category
```

Total rutas: **42** (antes 36).

#### Vista nueva

- **`categories/index.blade.php`** — tabla con:
  - Edición inline de nombre (Alpine.js `categoryRow()`, AJAX PATCH)
  - Conteo de productos asignados (badge)
  - Toggle activa/inactiva
  - Eliminar con confirm dinámico: avisa cuántos productos quedarán sin categoría

#### Vista modificada

- **`products/index.blade.php`** — actualizaciones:
  - Barra de filtro ampliada con select de **Categoría**
  - Nueva columna "Categoría" en la tabla de productos
  - Dropdown inline para asignar/cambiar categoría sin recargar (AJAX)
  - Enlace "Gestionar categorías →" en el header
  - Formulario de creación incluye selector de categoría

#### Resumen de artefactos

| Tipo | Antes | Ahora |
|------|-------|-------|
| Tablas DB | 14 | 16 |
| Modelos | 9 | 10 |
| Controladores | 10 | 11 |
| Rutas | 36 | 42 |
| Vistas | 12 pantallas | 13 pantallas |

---

## Sesión 2026-02-27 — Bug fix: precio inline no cerraba edición al guardar

### Commit de esta sesión

| Commit | Descripción |
|--------|------------|
| *(pendiente)* | fix(products): add Accept header to savePrice fetch so edit mode exits on success |

---

### fix: `savePrice()` quedaba en modo edición tras guardar exitosamente

**Archivo modificado:** `resources/views/products/index.blade.php`

#### Causa raíz

`savePrice()` hacía `fetch` con header `Content-Type: application/json` pero **sin** `Accept: application/json`.
`$request->wantsJson()` en `ProductController::updatePrice()` verifica ese header — al no estar presente devolvía `redirect()->back()` (302) en lugar de JSON.
El navegador seguía la redirección y recibía HTML; `res.json()` lanzaba `SyntaxError`; el bloque `if (data.success)` nunca ejecutaba; `editingPrice` se quedaba `true` y el input permanecía visible.

Los otros dos endpoints inline (`saveName`, `saveCategory`) no tenían el problema porque sus controladores siempre retornan `response()->json(...)` incondicionalmente.

#### Cambios

| Zona | Antes | Después |
|------|-------|---------|
| `savePrice()` headers | solo `Content-Type` | + `Accept: application/json` (fix raíz) |
| Manejo de error | ninguno — fallo silencioso | `try/catch` + estado `priceError` |
| Caso error | edit mode bloqueado sin feedback | edit mode permanece abierto, muestra mensaje en rojo |
| Cancelar / Escape | solo `editingPrice=false` | también limpia `priceError` |
| Indicador guardado | `ml-1` inline | `block mt-0.5` para no solapar la fila del input |

---

### Fase 2 (después de MVP estable en producción)

- ~~Precios especiales por cliente/producto~~ **HECHO** — commit `4ff6e30`
- Anulación de facturas (`voided` flag ya está en schema)
- Historial de cambios de precio
- CRUD de usuarios
- Exportar reporte a CSV
- Backup automático diario (scheduler Laravel)
- Link Click-to-WhatsApp con resumen de factura

### Fase 3 (nube + DIAN)

- Deploy VPS (Ubuntu 22.04, Nginx, PHP-FPM, PostgreSQL managed)
- `PRINT_JOB_SOURCE=api` → print daemon hace HTTP polling al cloud
- Integración DIAN API para emisión automática de FE y recepción de CUFE
- Confirmar con contador la clasificación IVA de carne antes de implementar

---

## Sesión 2026-02-25 — Control de versiones

### Git + GitHub configurado

- **Git identity** configurada globalmente: `Rodrigo Barrios <rogonec@gmail.com>`
- **Repositorio remoto** vinculado: `https://github.com/rodrigobs93/DondavidSoftware.git`
- **`.gitignore` raíz** creado para excluir `.claude/` (configuración local de Claude Code)
- `app/.git` (inicializado pero sin commits) eliminado para que `app/` sea parte del repo principal
- **Commit inicial** creado con 114 archivos (todo el proyecto)
- **Push exitoso** a `origin/main` — código ya visible en GitHub

---

## Estado actual del repositorio

```
donDavidSoftware/
├── app/                          ← Proyecto Laravel completo
│   ├── app/
│   │   ├── Console/Commands/PrintWorker.php
│   │   ├── Http/
│   │   │   ├── Controllers/      ← 11 controladores (+ CategoryController)
│   │   │   └── Middleware/       ← EnsureLanAccess, EnsureAdmin
│   │   ├── Models/               ← 10 modelos Eloquent (+ ProductCategory)
│   │   └── Services/             ← SaleService, EscPosTicketRenderer
│   ├── bootstrap/app.php         ← Aliases middleware lan + admin
│   ├── database/
│   │   ├── migrations/           ← 16 migraciones (14 aplicadas ✓ + 2 pendientes)
│   │   └── seeders/              ← 4 seeders (todos aplicados ✓)
│   ├── resources/views/          ← 13 pantallas Blade + Alpine.js
│   ├── routes/web.php            ← 42 rutas
│   └── .env                      ← Configurado para don_david DB
├── README-INSTALL.md             ← Guía de instalación Windows
├── .env.example                  ← Template documentado
├── setup.sh                      ← Creó el proyecto Laravel
├── setup_db.sh                   ← Creó DB y usuario PostgreSQL
├── run_migrate.sh                ← Corre migraciones + seeders
├── run_test.sh                   ← Verifica routes, key, extensiones
└── historic_notes.md             ← Este archivo
```

**Credenciales de acceso:**

| Usuario | Email | Contraseña | Rol |
|---------|-------|-----------|-----|
| Administrador | admin@dondavid.co | DonDavid2024! | Admin |
| Cajero | cajero@dondavid.co | Cajero2024! | Cashier |

**Para iniciar la app manualmente:**
```bash
# Terminal 1
cd "C:/Users/rodri/OneDrive/COLDEVS/donDavidSoftware/app"
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2
php artisan app:print-worker
```
Acceso: http://localhost:8000

---

## Sesión 2026-02-25 — Mejoras módulos Products y Customers

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `be0411a` | fix: replace mb_str_pad() with PHP 8.2-compatible manual padding |
| `5856906` | feat(products): add inline name edit and safe delete |
| `4ff6e30` | feat(customers): add business_name field and special prices per customer |

---

### feat: Products — edición de nombre + eliminación segura

**Archivos modificados:**
- `app/Models/Product.php` — añadido `SoftDeletes` trait + `invoiceItems()` hasMany
- `app/Http/Controllers/ProductController.php` — nuevos métodos `updateName()` y `destroy()`
- `routes/web.php` — rutas `POST /products/{id}/name` y `DELETE /products/{id}`
- `resources/views/products/index.blade.php` — componente Alpine.js `productRow()` unificado
- `database/migrations/2026_02_25_000000_add_deleted_at_to_products_table.php` — añade `deleted_at`

**Comportamiento:**
- Nombre: clic en la celda abre input inline; Enter/OK guarda vía AJAX; Escape cancela
- Eliminar: si el producto tiene historial en `invoice_items` → soft-delete (fila preservada en BD); si nunca fue usado → hard-delete
- Productos soft-deleted quedan excluidos automáticamente del autocomplete de ventas (SoftDeletes trait)

---

### feat: Customers — campo business_name + precios especiales

#### Campo business_name

**Archivos modificados:**
- `database/migrations/2026_02_25_000001_add_business_name_to_customers_table.php` — añade columna `business_name VARCHAR(150) NULL`
- `app/Models/Customer.php` — añadido a `$fillable`
- `app/Http/Controllers/CustomerController.php` — validación `Rule::requiredIf(doc_type === 'NIT')` en `store()` y `update()`
- `resources/views/customers/_form.blade.php` — nuevo campo con Alpine.js reactivo: asterisco rojo y hint aparecen al seleccionar NIT; campo se vuelve `required` en HTML
- `app/Services/SaleService.php` — `business_name` añadido al payload JSONB del print_job
- `app/Services/EscPosTicketRenderer.php` — imprime línea `Empresa: ...` en el tiquete cuando el cliente tiene `business_name` (solo en facturas con FE)

#### Precios especiales por cliente

**Archivos nuevos/modificados:**
- `app/Models/CustomerProductPrice.php` — nuevo modelo con relaciones `belongsTo` Customer y Product
- `app/Models/Customer.php` — añadida relación `specialPrices()` hasMany
- `app/Http/Controllers/CustomerController.php` — métodos `getPrices()`, `upsertPrice()`, `deletePrice()`
- `routes/web.php`:
  - `GET /customers/{id}/prices` → grupo auth+lan (el cajero lo necesita al crear ventas)
  - `POST /customers/{id}/prices` y `DELETE /customers/{id}/prices/{product}` → grupo admin
- `resources/views/customers/edit.blade.php` — nueva tarjeta "Precios especiales" con:
  - Tabla de precios existentes con botón Quitar
  - Buscador de producto + input de precio + botón Guardar (upsert vía AJAX)
  - Pre-rellena el precio si el producto ya tiene precio especial
- `resources/views/sales/create.blade.php`:
  - `selectCustomer()` ahora hace fetch de `/customers/{id}/prices` y cachea en `customPrices {}`
  - `addProductItem()` usa `customPrices[p.id] ?? base_price` como precio efectivo
  - Guarda `base_price` en cada item para poder revertir si cambia el cliente
  - Al cambiar cliente, re-pricings todos los items ya en el carrito
  - Badge "precio especial" en morado para items con precio especial activo
