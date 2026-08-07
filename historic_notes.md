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
| `d65950d` | feat(categories): add product categories module with inline editing |

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
| `d87e099` | fix(products): add Accept header to savePrice fetch so edit mode exits on success |

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

## Sesión 2026-02-27 — Mejoras módulo Clientes (búsqueda live + eliminación segura)

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `a0837de` | feat(customers): live search bar + safe delete with soft-delete + fix Invoice::customer withTrashed |

---

### feat: Búsqueda en tiempo real en lista de clientes

**Archivos modificados:**
- `app/Http/Controllers/CustomerController.php` — `index()` refactorizado
- `resources/views/customers/index.blade.php` — convertido a Alpine.js reactivo

#### Comportamiento

- Input único busca en `name` OR `business_name` (`ilike`, partial match, case-insensitive PostgreSQL)
- Debounce 400 ms — no hace request por cada tecla
- Mientras escribe: spinner "Buscando…" + tabla con `opacity-50`
- Sin resultados: fila vacía "No se encontraron clientes."
- Botón "Limpiar": visible solo cuando hay término activo; al pulsarlo restaura estado inicial sin fetch adicional
- URL actualizada con `history.replaceState` (`/customers?search=...`)
- Paginación visible solo cuando `!searching`; paginador usa `withQueryString()` para conservar el término en los links de página

#### Cambios técnicos

- `CustomerController::index()` ahora acepta `?search=`, filtra, y cuando `wantsJson()` devuelve array plano (sin paginar)
- Vista convierte el `@foreach` Blade a `<template x-for>` alimentado por `__initialCustomers` (JSON del primer render) sin request extra en carga inicial
- Campos en JSON: `id, name, is_generic, doc_label, phone, requires_fe, active`
- `$customers->paginate(30)->withQueryString()` — los links de paginación preservan `?search=`

---

### feat: Eliminación segura de clientes (soft delete)

**Archivos nuevos/modificados:**

| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_02_27_000004_add_deleted_at_to_customers_table.php` | Nueva migración: añade `deleted_at TIMESTAMPTZ NULL` con `softDeletesTz()` |
| `app/Models/Customer.php` | Añadido trait `SoftDeletes` |
| `app/Http/Controllers/CustomerController.php` | Nuevo método `destroy()` |
| `routes/web.php` | Nueva ruta `DELETE /customers/{customer}` en grupo admin |
| `resources/views/customers/index.blade.php` | Botón "Eliminar" con confirm dialog |

#### Lógica de eliminación (`destroy()`)

| Caso | Acción | Mensaje flash |
|------|--------|---------------|
| `is_generic` | `abort(403)` | — |
| Tiene facturas | `$customer->delete()` (soft) | "historial de facturas se conserva" |
| Sin facturas | `$customer->forceDelete()` | "eliminado definitivamente" |

#### Efectos del SoftDeletes trait (automáticos, sin cambios adicionales)

- `Customer::all()`, `CustomerController::index()`, `CustomerController::search()` → excluyen automáticamente clientes eliminados (scope global `deleted_at IS NULL`)
- Autocomplete de ventas (`/customers/search`) → excluye eliminados sin cambio de código
- Cartera, FE pendiente, reportes → no muestran clientes eliminados en nuevas selecciones

#### Seguridad

- Ruta `DELETE /customers/{customer}` solo en grupo `middleware('admin')` → cajero no puede acceder

---

### fix: `Invoice::customer()` devolvía `null` tras soft-delete de cliente

**Archivo modificado:** `app/Models/Invoice.php`

**Causa:** Al añadir `SoftDeletes` al modelo `Customer`, Eloquent añade el scope global `WHERE deleted_at IS NULL` a todas las queries, incluyendo las relaciones. Al cargar `$invoice->customer`, el JOIN filtraba el cliente eliminado y devolvía `null`, causando `Attempt to read property "name" on null` en `/invoices`.

**Fix:** `Invoice::customer()` ahora usa `->withTrashed()` para bypass del scope de soft-delete en esa relación específica. Semánticamente correcto: una factura siempre tiene un cliente (FK NOT NULL), y ese cliente debe poder recuperarse incluso si fue eliminado del sistema.

```php
// Invoice.php
public function customer()
{
    return $this->belongsTo(Customer::class)->withTrashed();
}
```

**Otras relaciones analizadas:** `CustomerProductPrice::customer()` no necesita `withTrashed()` porque solo se accede a través de route model binding desde un `Customer` vivo.

---

### Resumen de artefactos tras esta sesión

| Tipo | Antes | Ahora |
|------|-------|-------|
| Migraciones | 16 (14 aplicadas) | 17 (todas aplicadas ✓) |
| Rutas | 42 | 43 |

---

---

## Sesión 2026-02-27 — Facturas: búsqueda live + filtros de fecha y estado

### Commit de esta sesión

| Commit | Descripción |
|--------|------------|
| `b864167` | feat(invoices): live search + date range + status filter |

---

### feat: Lista de facturas — búsqueda en tiempo real + filtros

**Archivos modificados:**
- `app/Http/Controllers/InvoiceController.php` — `index()` refactorizado
- `resources/views/invoices/index.blade.php` — convertido a Alpine.js reactivo

#### Comportamiento

- **Barra de búsqueda:** busca en `consecutive` OR `customer.name` OR `customer.business_name` (`ilike`, parcial, case-insensitive PostgreSQL)
- **Filtros de fecha:** campos Desde/Hasta; acepta solo uno de los dos o ambos (rango inclusivo)
- **Chips de estado:** Todas / Pagadas / Parciales / Pendientes — activo resaltado en azul
- Debounce 400 ms en el input de texto; date pickers y chips disparan fetch inmediato
- Mientras carga: spinner "Buscando…" + tabla con `opacity-50 pointer-events-none`
- Botón **Limpiar**: visible solo cuando `hasFilters` (computed getter Alpine.js); restaura `__initialInvoices` sin fetch extra
- URL actualizada con `history.replaceState` para que el navegador refleje los filtros activos
- **Paginación:** visible solo cuando `!hasFilters`; al activar cualquier filtro se oculta

#### Cambios técnicos — `InvoiceController::index()`

| Parámetro | Fuente | Descripción |
|-----------|--------|-------------|
| `q` | `?q=` | Busca en `consecutive` + `whereHas('customer', withTrashed)` en `name` y `business_name` |
| `status` | `?status=` | `PAID`, `PARTIAL`, `PENDING` o vacío (todas) |
| `start_date` | `?start_date=` | `whereDate('invoice_date', '>=', ...)` |
| `end_date` | `?end_date=` | `whereDate('invoice_date', '<=', ...)` |

- `wantsJson()` → devuelve array plano; acepta header `Accept: application/json`
- `paginate(20)->withQueryString()` para preservar filtros en los links de paginación (modo HTML)
- `whereHas` + `withTrashed()` permite buscar por nombre de clientes soft-deleted sin romper la query

#### Cambios técnicos — `invoices/index.blade.php`

- Componente `invoiceFilter()` con bridge PHP→JS:
  - `__initialInvoices = {!! json_encode($initialData, JSON_HEX_TAG) !!}` — datos del primer render
  - `__initialQ/Status/StartDate/EndDate = @js(...)` — valores iniciales de URL
- Tabla `<template x-for="inv in invoices">` — reactive, sin Blade `@foreach`
- Badges de estado con `:class` object binding (`badge-paid`, `badge-partial`, `badge-pending`)
- Badges FE con `:class` (`bg-green-100 ISSUED` / `bg-blue-100 PENDING` / `bg-gray-100 NONE`)
- Función `fmt(val)` — formato moneda COP: `$` + `toLocaleString('es-CO', { maximumFractionDigits: 0 })`

---

---

## Sesión 2026-03-02 — Live search + filtros de fecha para Cartera y FE

### Commit de esta sesión

| Commit | Descripción |
|--------|------------|
| `781e3a3` | feat(cartera,fe): live search + date filters matching invoices pattern |

---

### Arquitectura compartida (refactoring)

#### Scope `Invoice::scopeApplyFilters`

Extraída la lógica de filtros de `InvoiceController` a un Local Scope de Eloquent reutilizable:

```php
// Invoice::scopeApplyFilters($query, $q, $startDate, $endDate)
// Busca en: consecutive, customer.name, customer.business_name (ilike + withTrashed)
// Filtra por fecha: solo startDate, solo endDate, o rango inclusivo ambos
```

Usada ahora por `InvoiceController`, `CarteraController` y `FePendingController`.

#### Blade partial `resources/views/partials/_filter-bar.blade.php`

Componente reutilizable con: input de búsqueda (debounce 400ms), date pickers Desde/Hasta, botón Limpiar (`x-show="hasFilters"`), spinner `Buscando…`.

Contrato Alpine.js: el componente padre debe exponer `q`, `startDate`, `endDate`, `loading`, `hasFilters`, `search()`, `clearFilters()`.

Variable Blade opcional: `$placeholder` para personalizar el placeholder del input.

---

### feat: Cartera — búsqueda live + filtros de fecha

**Archivos modificados:**
- `app/Http/Controllers/CarteraController.php` — `index()` refactorizado
- `resources/views/cartera/index.blade.php` — convertido a Alpine.js reactivo

#### Comportamiento

- Búsqueda en tiempo real (debounce 400ms): consecutive + customer.name + customer.business_name
- Filtros de fecha Desde/Hasta (misma semántica que Invoices)
- Paginación oculta cuando `hasFilters` activo
- Botón Limpiar restaura datos iniciales sin fetch extra
- URL sincronizada con `history.replaceState`
- **Saldo filtrado:** contador reactivo que suma `balance` de los registros visibles (`filteredBalance()`)
- **Total saldo global** en el header siempre refleja toda la cartera (calculado en PHP, no afectado por filtros Alpine)

#### Técnico

- Cartera conserva layout de **tarjetas** (no tabla): `x-for` con `x-data="{ showAbono: false }"` anidado para toggle por fila
- Formulario de abono usa `:action` dinámico + `__csrf` desde meta tag para token CSRF
- No hay chips de estado (toda la base dataset ya es PARTIAL/PENDING por `balance > 0`)
- `toRow` expone: `id, consecutive, invoice_date, customer_name, total, paid_amount, balance, status`

---

### feat: FE — búsqueda live + filtros de fecha + chips de estado FE

**Archivos modificados:**
- `app/Http/Controllers/FePendingController.php` — refactorizado, acepta `Request`
- `resources/views/fe-pending/index.blade.php` — convertido a Alpine.js reactivo

#### Comportamiento

- Base dataset cambiada: ahora `requires_fe = true` (antes solo `fe_status = PENDING`)
  → La vista muestra **todas las FE** (pendientes + emitidas)
- Búsqueda en tiempo real: mismo patrón que Cartera e Invoices
- Filtros de fecha: mismo comportamiento
- **Chips de estado FE:** Todas / Pendientes / Emitidas
- Paginación condicional, URL sync, Limpiar

#### Técnico

- `toRow` expone: `id, consecutive, invoice_date, customer_name, customer_doc, total, fe_status, fe_reference`
- Badge por `fe_status`: `ISSUED` → verde, `PENDING` → azul
- Componente Alpine: `feFilter()` con `feStatus` como filtro adicional

---

### Resumen de artefactos tras esta sesión

| Tipo | Antes | Ahora |
|------|-------|-------|
| Vistas | 13 pantallas | 13 pantallas (Cartera + FE reescritas con Alpine.js) |
| Partials | — | 1 nuevo (`partials/_filter-bar.blade.php`) |
| Model scopes | — | 1 nuevo (`Invoice::scopeApplyFilters`) |

---

---

## Sesión 2026-03-03 — Fixes clientes y ventas + mejoras búsqueda

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `96948b7` | fix(customers): allow re-creating soft-deleted docs via partial unique index |
| `06c41a3` | feat(customers): add business_name column to customers list table |
| `58ac17e` | feat(sales): extend customer search to include business_name |
| `156785b` | fix(sales): allow PENDING/PARTIAL sales, fix validation.min.numeric error |

---

### fix: No se podía recrear cliente con mismo documento (UniqueConstraintViolationException)

**Archivos:**
- `database/migrations/2026_02_27_000005_fix_customers_doc_unique_partial.php` — nueva migración
- `app/Http/Controllers/CustomerController.php` — validación actualizada en `store()` y `update()`

**Causa:** El constraint `uq_customers_doc UNIQUE (doc_type, doc_number)` era global (cubría filas soft-deleted). Al eliminar un cliente y recrearlo con el mismo documento, PostgreSQL lanzaba `SQLSTATE[23505]`.

**Fix DB:** Migración `000005` elimina el constraint completo y lo reemplaza con índice parcial:
```sql
CREATE UNIQUE INDEX uq_customers_doc ON customers (doc_type, doc_number) WHERE deleted_at IS NULL;
```

**Fix App:** `store()` y `update()` usan `Rule::unique(...)->whereNull('deleted_at')` para que Laravel valide la unicidad solo entre clientes activos (evita el error de DB y muestra mensaje amigable).

---

### feat: Columna "Razón social" en lista de clientes

**Archivos:**
- `app/Http/Controllers/CustomerController.php` — `business_name` añadido al `$toRow`
- `resources/views/customers/index.blade.php` — nueva columna entre Nombre y Documento

**Comportamiento:** Columna "Razón social" visible en pantallas `sm:` y arriba; oculta en móvil. Muestra `—` si el cliente no tiene razón social. Texto truncado con `max-w-56 truncate`. `colspan` de la fila vacía actualizado de 6 → 7.

---

### feat: Autocomplete de clientes en Nueva Venta busca también por razón social

**Archivos:**
- `app/Http/Controllers/CustomerController.php` — `search()` extendido
- `resources/views/sales/create.blade.php` — dropdown muestra razón social

**Cambios `search()`:** Añadido `orWhere('business_name', 'ilike', "%{$q}%")` + `business_name` en el `get([...])` de columnas.

**Dropdown actualizado:** El resultado muestra `· Razón social` en gris itálico junto al nombre cuando el cliente la tiene. Ejemplo: `Rodrigo Barrios · Restaurante Don David  (NIT 123456789)`.

---

### fix: Ventas PENDIENTE/PARCIAL bloqueadas — error "validation.min.numeric"

**Archivos:**
- `app/Http/Controllers/SaleController.php` — reglas de validación + filtrado de pagos
- `resources/views/sales/create.blade.php` — botón submit refleja estado de pago

**Causa raíz:** La regla `payments.*.amount => min:0.01` rechazaba el row de pago placeholder que el frontend siempre envía con `amount: 0`. Sin traducciones de Laravel en español, el error se mostraba como clave cruda `validation.min.numeric`. El error también bloqueaba ventas PENDIENTE (sin pago) y PARCIAL (primer row con monto=0).

**Fixes en `SaleController`:**
1. `payments` cambiado de `required|array|min:1` → `nullable|array`
2. `payments.*.amount` cambiado de `min:0.01` → `min:0`
3. Después de validar: filtra rows con `amount == 0` vía `array_filter + bccomp`; array vacío → SaleService recibe `[]` → status PENDING
4. Array de mensajes en español añadido para todos los campos

**SaleService sin cambios** — ya manejaba los 3 estados correctamente (PAID/PARTIAL/PENDING en líneas 42-46).

**Botón submit actualizado:**
| Estado | Color | Texto |
|--------|-------|-------|
| Sin productos | Gris | "Agrega al menos un producto" |
| Sobrepago | Gris | "Pago inválido — ajusta los montos" |
| Error FE | Gris | "Error en FE" |
| `balance = 0` | **Verde** | "Finalizar Venta PAGADA — $X" |
| `paidAmount > 0, balance > 0` | **Amarillo** | "Finalizar Venta PARCIAL — abona $X" |
| `paidAmount = 0` | **Amarillo** | "Finalizar Venta PENDIENTE — $X por cobrar" |

**Checklist QA ventas (verificado en código):**

| Escenario | Resultado |
|-----------|-----------|
| Producto KG cantidad decimal (1.250 kg) | ✅ pasa `min:0.001` |
| Producto UNIT cantidad entera (4 und) | ✅ |
| Pago completo (1 método) | ✅ → PAID |
| Split payment suma = total | ✅ → PAID |
| Pago parcial (amount < total) | ✅ → PARTIAL |
| Sin pago (amount = 0) | ✅ → PENDING (antes bloqueado) |
| Domicilio 0 / activo | ✅ suma al total |
| FE = No | ✅ fe_status = NONE |
| FE = Sí cliente con NIT | ✅ fe_status = PENDING |
| FE = Sí cliente genérico | ✅ bloqueado (frontend + backend) |
| Sobrepago | ✅ bloqueado por `overpay` check |
| Error muestra texto español | ✅ (antes mostraba clave raw) |

---

---

## Sesión 2026-03-03 (parte 2) — FE auto-check + Módulo Validación de Pagos

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `1c0e234` | fix(sales): auto-check FE on customer select + fix validation.boolean |
| `dce063b` | feat(reports): payment verification workflow |
| `4935ecd` | feat(reports): exclude cash payments from verification report |
| `c15fc93` | ui: rename Reportes → Validación de Pagos |

---

### fix: Auto-check FE al seleccionar cliente + error "validation.boolean"

**Archivo:** `resources/views/sales/create.blade.php`

#### Issue A — Auto-check FE
`selectCustomer(c)` llamaba `onFeToggle()` para validar el estado, pero nunca leía `c.requires_fe`. Si el cliente tenía `requires_fe = true`, el toggle quedaba sin marcar y el usuario debía activarlo manualmente.

**Fix:** Una línea antes de `onFeToggle()`:
```js
this.requiresFe = c.requires_fe || false;
```
Esto también resetea a `false` cuando se cambia a un cliente sin FE requerida.

#### Issue B — validation.boolean
El checkbox `<input type="checkbox" name="requires_fe">` enviaba `"on"` al marcarse (comportamiento HTML estándar). La regla `['boolean']` de Laravel acepta `true/false/1/0/"1"/"0"` pero **no** `"on"`.

**Fix:** Se retiró el atributo `name` del checkbox (que solo controla el estado Alpine) y se añadió un `<input type="hidden">` que siempre envía `0` o `1`:
```html
<input type="checkbox" x-model="requiresFe" @change="onFeToggle()" class="rounded">
<input type="hidden" name="requires_fe" :value="requiresFe ? 1 : 0">
```

**Checklist QA FE (verificado en código):**

| Escenario | Resultado esperado |
|-----------|--------------------|
| Seleccionar cliente con `requires_fe = true` | Toggle FE se marca automáticamente |
| Cambiar a cliente sin `requires_fe` | Toggle FE se desmarca automáticamente |
| FE off → finalizar venta | `requires_fe=0`, `fe_status=NONE`, sin error |
| FE on + cliente válido + NIT → finalizar | `requires_fe=1`, `fe_status=PENDING`, aparece en módulo FE |
| FE on + cliente GENÉRICO | Frontend bloquea (feError visible), backend también rechaza |
| FE on + cliente sin documento | Frontend bloquea, backend rechaza con mensaje |

---

### feat: Módulo "Validación de Pagos" (`/reports/payments`)

Reemplazo completo del antiguo reporte estático de pagos. El módulo pasa a ser una herramienta de **conciliación** de pagos electrónicos con arquitectura idéntica a Facturas/Cartera (live search + wantsJson).

#### Migración nueva

**`2026_03_03_000006_add_verification_to_payments.php`**

| Columna nueva | Tipo | Descripción |
|---------------|------|-------------|
| `verified` | `boolean DEFAULT false` | Flag de conciliación |
| `verified_at` | `timestampTz nullable` | Cuándo se verificó |
| `verified_by_user_id` | `FK → users nullable` | Quién verificó |
| `updated_at` | `timestampTz nullable` | Ahora el modelo rastrea updates |

Índice compuesto añadido: `idx_payments_verified_paid_at (verified, paid_at)` — cubre el orden default `verified ASC, paid_at DESC` y el filtro de no verificados.

**Modelo `Payment` actualizado:**
- Eliminado `const UPDATED_AT = null`; ahora `const UPDATED_AT = 'updated_at'`
- Nuevos fillable: `verified`, `verified_at`, `verified_by_user_id`
- Nuevos casts: `verified => boolean`, `verified_at => datetime`
- Nueva relación: `verifiedBy()` → belongsTo User

#### Rutas nuevas (admin-only)

```
PATCH  /payments/{payment}/verify    payments.verify
POST   /payments/verify-bulk         payments.verify-bulk
```

#### Controlador `ReportController` — 3 métodos

**`payments(Request $request)` — reescrito completo:**
- Parámetros: `q`, `start_date`, `end_date`, `method`, `unverified_only`
- Base query: excluye CASH (`where('method', '!=', 'CASH')`), excluye facturas anuladas, orden default unverified primero
- Búsqueda: consecutive OR customer.name OR customer.business_name (vía `whereHas` anidado con `withTrashed`)
- Dual response: `wantsJson()` → JSON plano; HTML → `paginate(50)` + datos iniciales para Alpine
- `$toRow`: `id, invoice_id, consecutive, customer_name, business_name, method, method_label, amount, paid_at, verified, verified_at`

**`verifyPayment(Payment $payment)` — PATCH individual:**
- Idempotente (re-verificar actualiza `verified_at`)
- Devuelve JSON `{ ok: true, verified_at: "dd/mm/YYYY HH:mm" }`

**`verifyBulk(Request $request)` — POST bulk:**
- Valida `ids[]` array de enteros
- Actualiza solo filas con `verified = false` dentro de los IDs dados
- Devuelve JSON `{ ok: true, count: N }`

#### Vista `reports/payments.blade.php` — reescrita completa

Componente Alpine `paymentReport()` con:

| Elemento | Descripción |
|----------|-------------|
| `_filter-bar` partial | Búsqueda + fechas Desde/Hasta + Limpiar + spinner |
| Chips de método | Todos / Tarjeta / Nequi / Daviplata / Bre-B (sin Efectivo) |
| Toggle "Solo no verificados" | Chip amarillo ON/OFF |
| Bulk action bar | Aparece cuando hay checkboxes marcados; botón "Verificar seleccionados" |
| Tabla | Checkbox · # · Fecha pago · Cliente · Razón social (hidden sm) · Método · Monto · Estado · Acción |
| Badges método | Colores por método (verde=CASH excluido, azul=Tarjeta, rosa=Nequi, rojo=Daviplata, morado=Bre-B) |
| Badge estado | Amarillo "Pendiente" / Verde "✓ Verificado" |
| Botón "Verificar" | AJAX PATCH individual; actualiza fila in-place sin recarga |
| Bulk verify | POST JSON; hace re-fetch tras éxito para traer `verified_at` del servidor |
| Footer | Count total + "N sin verificar" + suma de montos visibles |
| Select all | Checkbox en header selecciona todos los no verificados |

**Gestión de estado `selected` (Set):** Alpine no detecta mutaciones de Set nativas; se reasigna `this.selected = new Set(...)` en cada cambio para forzar reactividad.

**Nombre del módulo:** Nav label → **"Validación"**; page title y `<h1>` → **"Validación de Pagos"**. Ruta `/reports/payments` sin cambio.

---

### Casos borde cubiertos

| Caso | Comportamiento |
|------|----------------|
| Pago ya verificado incluido en bulk | `WHERE verified = false` — se omite silenciosamente; `count` refleja solo los nuevos |
| Factura anulada | Excluida del query base (`whereHas voided=false`) |
| Cliente soft-deleted | `withTrashed()` en eager load — nombre sigue visible |
| Pagos en efectivo | Excluidos de la vista (no requieren conciliación) |
| Split payment (varios métodos) | Cada fila Payment es independiente; se verifican por separado |

---

---

## Sesión 2026-03-03 (parte 3) — Mejoras input KG en Nueva Venta

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `fdda5dc` | feat(sales): KG quantity input in grams with thousand-separator display |
| `21d76dd` | feat(sales): KG quantity starts empty instead of 1 kg |

---

### feat: Input de cantidad KG — ingreso en gramos con separadores de miles

**Archivo:** `resources/views/sales/create.blade.php`

#### Motivación

El flujo original de Nueva Venta para productos KG pedía al usuario ingresar la cantidad en kg con decimales (p.ej. `1.250`). Esto generaba fricciones:
- El usuario debe calcular mentalmente kg desde gramos
- `type="number"` con `step="0.001"` es difícil de usar en pantallas táctiles
- No había retroalimentación visual de separadores de miles

#### Comportamiento nuevo

| Acción del usuario | Resultado visible | `item.quantity` (kg) |
|---|---|---|
| Agrega producto KG | Campo vacío, label `g` | 0 |
| Escribe `500` | `500` mientras escribe | 0.5 |
| Pierde foco | `500` (sin separador, < 1000) | 0.5 |
| Escribe `1250` | `1.250` al perder foco | 1.25 |
| Escribe `10000` | `10.000` al perder foco | 10.0 |
| Borra todo | campo vacío | 0 |
| Escribe `0` | campo `0`, borde rojo | 0 |

#### Implementación técnica

**Dos inputs separados con `x-show` (reemplaza el único `type="number"`):**

```html
{{-- KG: text input, usuario ingresa gramos --}}
<input x-show="item.sale_unit === 'KG'"
    type="text" inputmode="numeric"
    x-init="$el.value = formatGrams(item.quantity)"
    @focus="$el.value = String(Math.round(item.quantity * 1000) || '')"
    @input="onGramsInput(item, $event)"
    @blur="$el.value = formatGrams(item.quantity)"
    ...>

{{-- UNIT: input número entero sin cambios --}}
<input x-show="item.sale_unit !== 'KG'"
    type="number" x-model.number="item.quantity"
    @input="computeLineTotal(item)"
    step="1" min="1" ...>
```

**Por qué `x-init` + eventos manuales en lugar de `:value` reactivo:**
Alpine actualizaría el `el.value` reactivamente en cada keystroke (cuando `item.quantity` cambia), reposicionando el cursor al final. Usando `x-init` (solo en mount) + eventos `@focus/@blur/@input` manuales, el DOM solo se actualiza en momentos controlados y el cursor no salta.

**Flujo de eventos KG:**
1. `@focus` → muestra entero sin puntos (`"1.250"` → `"1000"`)
2. `@input` → `onGramsInput()`: strip non-digits → `item.quantity = grams/1000` → recompute → error si `raw.length > 0 && qty < 0.001`
3. `@blur` → `formatGrams()`: `(1.25 * 1000).toLocaleString('es-CO')` = `"1.250"`

**Nuevas funciones Alpine:**

```js
formatGrams(qty) {
    const g = Math.round((parseFloat(qty) || 0) * 1000);
    return g > 0 ? g.toLocaleString('es-CO') : '';  // '' para campo vacío
},
onGramsInput(item, event) {
    const raw = event.target.value.replace(/[^0-9]/g, '');
    event.target.value = raw;                 // strip en lugar de cursor jump
    item.quantity = (parseInt(raw) || 0) / 1000;
    this.computeLineTotal(item);
    item.qtyError = raw.length > 0 && item.quantity < 0.001;
},
```

**Gestión de `qtyError` para KG:**
- `computeLineTotal` siempre pone `qtyError = false` en el `else` (revirtió la lógica del commit anterior que la ponía `true`)
- `onGramsInput` la sobreescribe DESPUÉS: error solo si el usuario escribió dígitos pero la cantidad es < 1 g
- Campo vacío (sin dígitos) → `raw.length === 0` → sin error → sin borde rojo

**Inicialización vacía:**
```js
quantity: p.sale_unit === 'KG' ? 0 : 1,
line_total: p.sale_unit === 'KG' ? 0 : effectivePrice,
```
- KG arranca en 0 → `formatGrams(0) = ''` → input vacío
- `line_total = 0` hasta que el usuario ingrese gramos
- `total = 0` → `canSubmit = false` → no se puede enviar accidentalmente

**Label cambiado:** `'kg'` → `'g'` bajo el input KG.

**Hidden input sin cambios:** `<input type="hidden" :value="item.quantity">` sigue enviando kg al servidor (decimal, hasta 3 decimales).

#### Invariantes preservadas

- Lógica de totales (`computeLineTotal`, `subtotal`, `total`, `balance`) sin cambios
- Productos UNIT: sin modificaciones (integer, `type="number"`, `x-model.number`)
- Servidor recibe `items[idx][quantity]` en kg (e.g. `1.25`)
- `InvoiceItem.quantity` almacenado en kg en PostgreSQL

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

---

## Sesión 2026-06-03 — Sistema de botones + refinamientos UI/impresión

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `b3e9449` | feat(ui): standardize button system + cartera ticket/dashboard/keyboard refinements |

---

### fix/feat: Sistema de botones unificado (fuente única de verdad)

**Causa raíz encontrada:** los botones se veían "planos/sin estilo" porque las
clases `.pos-btn-*`, `.form-input` y `.badge-*` estaban definidas con `@apply`
dentro de un `<style>` normal. La app usa el **Tailwind Play CDN**, que NO
procesa `@apply` en un `<style>` corriente (solo en `<style type="text/tailwindcss">`),
así que el navegador descartaba esas reglas como CSS inválido → los botones
quedaban sin estilo.

**Archivos modificados:**
- `resources/views/layouts/app.blade.php` — reescrito el sistema de botones como
  **CSS plano** (renderiza al instante, sin depender del CDN). Variantes
  `pos-btn-primary/secondary/success/danger/ghost` con altura mínima táctil de
  44px y estados `hover` / `active` / `:disabled` / `.is-loading` / `:focus-visible`
  (anillo de foco). Nuevos helpers compactos `.pos-btn-link(-danger)` para acciones
  en tablas y `.pos-btn-icon(-danger)` para botones de ícono (✕ / eliminar).
  También se convirtieron `.form-input` y `.badge-*` a CSS plano (misma causa).
- Barrido de vistas aplicando clases estándar:
  - `customers/edit.blade.php`, `customers/index.blade.php` — Editar/Quitar/
    Eliminar/Cancelar → `pos-btn-link` (variante danger en eliminar/quitar)
  - `categories/index.blade.php` — Activar/Desactivar + Eliminar → `pos-btn-link`
  - `invoices/index.blade.php` — acción "Ver" → `pos-btn-link`
  - `partials/_quick-sale-modal.blade.php`, `partials/_marquilla-modal.blade.php`,
    `sales/create.blade.php` — botones de cierre/eliminar (✕ / ×) → `pos-btn-icon`
  - `products/index.blade.php` — botón "Filtrar" ahora `:disabled` durante la
    carga (evita doble envío); muestra "Buscando…" con estado atenuado

**Semántica:** se mantiene `<button>` para acciones y `<a>` solo para navegación.
Las páginas de auth (login/forgot) se dejaron intactas: son documentos
independientes con su propio `<head>` y usan utilidades Tailwind directas.

### feat: Tiquete de cobro de Cartera con estilo de factura

**Archivo modificado:**
- `app/Services/EscPosTicketRenderer.php` — `renderCarteraResumen()` ahora usa la
  misma estrategia de "cuerpo centrado" que las facturas (`render()`): bloque
  centrado a 54 col, montos alineados a la derecha, divisores consistentes, y
  truncado de nombres largos con `...`. El logo, encabezado y pie ya eran
  compartidos. Sin cambios en el controlador ni en el payload.

### feat: Limpieza de dashboard + teclado táctil en móvil/tablet

**Archivos modificados:**
- `resources/views/dashboard.blade.php` — removido el panel "Acceso desde celular
  (misma red WiFi)" con la URL LAN de la pantalla de inicio.
- `resources/views/layouts/app.blade.php` — el teclado embebido se desactiva en
  móvil/tablet (viewport ≤1024px o user-agent móvil) mediante un guard
  `isHandheld()` en `isEligible()`. Los touchscreens de escritorio lo conservan;
  en dispositivos handheld se usa el teclado nativo del SO y los modales
  (Marquillas / Venta rápida) funcionan normalmente.

---

## Sesión 2026-06-03 (cont.) — Cliente en tiquete + tecla Done única

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `b3bb30e` | fix: always print customer on ticket + single touch-keyboard Done |

---

### fix: el tiquete de factura no imprimía Cliente / Empresa

**Causa raíz:** en `app/Services/EscPosTicketRenderer.php`, `render()` imprimía el
bloque Cliente/Empresa **solo** cuando `requires_fe && !is_generic`. Las ventas
normales POS (sin FE) — el caso común — nunca mostraban el cliente. El payload
siempre traía los datos (`SaleService::buildInvoicePayload`); el problema era la
plantilla.

**Fix (`app/Services/EscPosTicketRenderer.php`):**
- Ahora **siempre** se imprime `Cliente: <nombre>`; para clientes genéricos se
  imprime `Cliente: GENERICO` (el registro semilla se llama "CLIENTE GENÉRICO",
  que se leería redundante).
- `Empresa: <business_name>` se imprime solo cuando existe.
- La línea `Doc:` sigue limitada a facturas con FE y cliente no genérico (es fiscal).
- Nuevo helper `centeredWrapped()` para envolver (word-wrap) nombres/empresas
  largos sin cortes a mitad de palabra.
- Aplica a ventas nuevas y a reimpresiones (mismo renderer vía `createPrintJob`).

### fix: una sola tecla "Listo ✓" en el teclado táctil (sin impresión accidental)

**Bug previo:** tocar la tecla `{done}` del teclado embebido en `/sales/new`
cerraba el teclado en un `setTimeout(0)`, y el "ghost click" táctil caía sobre el
botón Finalizar → imprimía sin querer (tap-through, no era z-index ni
`data-kb-submit-on-done`).

**Fix (`resources/views/layouts/app.blade.php`):**
- Se eliminó la tecla `{done}` de los layouts numérico y QWERTY/shift/numbers.
  El único Done es el botón superior `#kb-close-btn` ("Listo ✓"), reestilizado
  como botón primario verde y **solo cierra** (nunca envía formularios ni imprime).
- Para no descuadrar la grilla al quitar Done, se colocó una tecla `{placeholder}`
  oculta y no interactiva en cada posición donde estaba Done: CSS
  `visibility:hidden; pointer-events:none`, más `aria-hidden="true"` y
  `tabindex="-1"` vía `buttonAttributes`. Mantiene el ancho/alto exacto.
- Se eliminaron `submitIfOptedIn()` / `suppressNextClick()` / el campo de store
  `lastClosedAt` (quedaron sin uso al quitar `{done}`) y el atributo inerte
  `data-kb-submit-on-done` en `products/index.blade.php` (el precio se guarda con
  su botón **OK**).
- `submitForm()` en `sales/create.blade.php`: guard simplificado a bloquear el
  envío **solo mientras el teclado está abierto** (`$store.keyboard.open`).

---

## Sesión 2026-06-04 — Mensajes de validación en español + iconos + layout de venta

### Commits de esta sesión

| Commit | Descripción |
|--------|------------|
| `b877775` | feat(i18n): Spanish validation messages (replace raw validation.* keys) |
| `5ead238` | feat(ui): icon components + pencil on price edit + heart on saldo a favor |
| `d44b1bf` | feat(ui): widen /sales/new right column + larger cart text (desktop) |

---

### feat: mensajes de validación en español (adiós a `validation.unique`)

**Causa raíz:** la app corre con `APP_LOCALE=es` **y** `APP_FALLBACK_LOCALE=es`,
pero **no existía un directorio `lang/`**. Laravel solo trae mensajes en inglés en
`vendor`, así que toda clave `validation.*` se mostraba cruda (p. ej. al guardar un
cliente con número de identificación duplicado salía literalmente
`validation.unique`). Afectaba **todos** los formularios.

**Fix (Laravel 12, lang path = `app/lang/`):**
- Nuevo `app/lang/es/validation.php` (traducción completa al español) con:
  - **`attributes`** — nombres de campo legibles (`doc_number`→"número de
    identificación", `email`→"correo electrónico", `base_price`→"precio",
    `amount`→"monto", etc.) para que los mensajes por defecto se lean naturales.
  - **`custom`** — `doc_number.unique` → "Ya existe un cliente con este número de
    identificación."; `name.unique` → "Ya existe una categoría con ese nombre."
- `resources/views/customers/_form.blade.php` — se agregó `@error` en línea para
  `doc_number` y `doc_type` (antes el error de duplicado solo salía en el resumen
  superior del layout). El input ya se preservaba vía `old()`.
- Es un arreglo a nivel de locale → también corrige Productos, Ventas, Cartera y
  precios especiales. Verificado con prueba real contra la BD: el duplicado emite
  el mensaje personalizado.
- Pendiente opcional (no hecho): copiar el archivo a `installer/payload/` al
  reconstruir el instalador; opcionalmente `APP_FALLBACK_LOCALE=en` como red de
  seguridad.

### feat: iconos (set reutilizable + lápiz en precio + corazón en saldo a favor)

- Nuevos **componentes Blade anónimos** en `resources/views/components/icon/`
  (SVG Heroicons en línea, sin librería ni build): `pencil`, `heart`, `check`,
  `x-mark`. Uso: `<x-icon.pencil class="w-4 h-4 text-gray-400" />` (color por
  `text-*` = currentColor, tamaño por `w-/h-*`; base `inline-block shrink-0`).
- `products/index.blade.php` — lápiz junto a cada precio (un toque abre la edición;
  el doble clic sigue funcionando); en modo edición los botones OK/✕ pasan a iconos
  check / x-mark. El móvil (tarjetas) no edita precio → sin cambios.
- `cartera/customer.blade.php` — corazón sólido antes de la etiqueta "Saldo a
  favor", verde cuando el cliente tiene saldo a favor y gris cuando es cero.
- Solo iconos; sin cambios de lógica.

### feat: /sales/new — columna derecha más ancha + texto del carrito más grande

- Solo escritorio: grilla `md:grid-cols-3` → `md:grid-cols-5`; izquierda
  `col-span-2`→`3` (60%), derecha (carrito/resumen/pagos/finalizar)
  `col-span-1`→`2` (40%). El apilado en móvil/tablet no cambia.
- Tipografía del carrito a `text-base` (nombre, precio unitario, total de línea) y
  cantidad a `text-sm`. Columnas numéricas ensanchadas (`w-20` cantidad, `w-28`
  total, `shrink-0`) e input de precio `flex-1 min-w-0` para evitar desbordes.
  Sin cambios de cálculo/guardado.

## Sesión 2026-06-05 — Módulo Proveedores / Cuentas por Pagar (FIFO + toggle + modal)

Reemplaza el cuaderno físico de facturas y deudas de proveedores por un módulo
local-first coherente con el resto del POS (mismos botones, badges, formato COP
`$1.000`, validación en español, tablas/tarjetas responsive, impresión térmica).

### Base de datos (7 migraciones, `2026_06_05_*`)
- `suppliers` — name, tax_id, phone, contact, notes, `credit_balance` (saldo a
  favor, CHECK ≥ 0), `active`, soft deletes.
- `supplier_invoices` — supplier_id, invoice_number (opcional), invoice_date,
  due_date (opcional), total_amount, paid_amount, balance, `status`
  (PENDING/PARTIAL/PAID), notes, voided.
- `supplier_invoice_items` — description, **`sale_unit` (KG|UNIT)** con CHECK,
  quantity (3 decimales para KG), unit_price (COP entero), line_total, sort_order.
- `supplier_payments` — **un solo registro por pago** (ej. Nequi $36.000);
  `supplier_invoice_id` nullable (modo A factura-ligada / modo B nivel-proveedor),
  method (CASH/NEQUI/DAVIPLATA/DAVIVIENDA/OTHER), `submission_key` único
  (anti doble-submit).
- `supplier_payment_allocations` — distribución FIFO para auditoría
  (supplier_payment_id, supplier_invoice_id, allocated_amount, created_at).
- Seed `module_suppliers_enabled` = `'0'` (apagado por defecto).

### Lógica (servicio + controladores)
- `SupplierPaymentService` (espejo de `CustomerPaymentService`), `DB::transaction`
  + `lockForUpdate` + bcmath:
  - `applyConsolidatedPayment` (modo B): distribuye el pago entre las facturas más
    antiguas primero (invoice_date, id); el sobrante se vuelve `credit_balance`
    (saldo a favor). Idempotente por `submission_key`.
  - `applyInvoicePayment` (modo A): paga una factura puntual; rechaza monto > saldo
    con "El abono no puede superar el saldo pendiente."
- Controladores: `SupplierController` (CRUD + detalle CxP + impresión),
  `SupplierInvoiceController` (alta de factura con ítems), `SupplierPaymentController`
  (pagos modo A/B). Todos responden **JSON** (201/422) cuando `expectsJson` para que
  el modal mantenga el estado y muestre errores por campo en español.

### Toggle del módulo
- Middleware `EnsureSuppliersEnabled` (alias `suppliers` en `bootstrap/app.php`):
  rutas bajo `['auth','lan','admin','suppliers']`. Si está apagado → 403 a URLs
  directas y "Proveedores" oculto en el navbar (desktop + hamburguesa).
- Switch en Config (`backups/index.blade.php` + whitelist en `BackupController`).

### Entrada en gramos para KG (reutilizada de /sales/new)
- Helpers compartidos **`window.KgGrams`** en el layout (rawGrams, toKg, toGrams,
  formatGrams, kgLabel). `/sales/new` se refactorizó para usarlos (sin cambio de
  comportamiento), eliminando duplicación.
- Partial reusable `partials/_kg-unit-qty.blade.php`: KG escribe **gramos** de la
  báscula (60000 = 60.000 kg), separador de miles, preview "Equivale a: X kg";
  internamente se guarda kg = gramos/1000. UNIT entero. `line_total = kg * precio`.

### Modal "+ Nueva factura" (touch-first)
- `partials/_supplier-invoice-modal.blade.php`, patrón de Venta Rápida/Marquillas
  (evento `open-supplier-invoice`, z-1000, offset de teclado, backdrop/X/Cancelar/
  Escape). Etapa 1: proveedor (bloqueado si viene del detalle, dropdown si viene de
  la lista), datos + tabla de ítems + total. Etapa 2 (opcional): "Guardar y registrar
  pago" → modo A (esta factura) o modo B (FIFO al proveedor). Éxito redirige al
  detalle; errores 422 mantienen el modal abierto.
- Botones de entrada en lista y detalle; se eliminó el formulario inline anterior.

### Impresión
- `EscPosTicketRenderer::renderSupplierConsolidado` (espejo de
  `renderCarteraResumen`): "PAGO A PROVEEDOR", facturas pendientes (n.º, fecha,
  saldo), Deuda total, Saldo a favor, **NETO A PAGAR**. Envío síncrono como cartera.

### Pruebas
- `tests/Feature/SupplierInvoiceModalTest.php` (7, DatabaseTransactions): alta con
  ítems KG+UNIT, total-only, 422 sin fecha, 422 sin total/ítems, pago FIFO,
  sobrepago factura (422 "saldo"), rutas bloqueadas con módulo apagado. **7/7 OK**.
- FIFO verificado aparte: parcial / pago exacto / sobrepago→saldo a favor /
  doble-submit idempotente.
- Correr tests apuntando a la BD dev: `DB_DATABASE=don_david DB_USERNAME=don_david_user
  DB_PASSWORD=don_david_pass php artisan test` (phpunit.xml apunta a `mi_pos`, inexistente).

### Pendiente / Fase 2
- Fotos/PDF de facturas de proveedor. Aplicar saldo a favor a una factura puntual
  (estilo Cartera) + ledger de movimientos. Incluir pagos de proveedor en Validación.

## Sesión 2026-06-15 — Reset de BD (local/test) + ítems de proveedor responsive

### Reset de base de datos a estado limpio (solo local/test)
- `scripts/reset-db.ps1` — comando único y repetible: `migrate:fresh --force` +
  seed mínimo. Guardas de seguridad: **bloquea** si `APP_ENV` no es `local`/`testing`
  (lee el entorno autoritativo vía artisan, no solo `.env`) y exige escribir `RESET`
  (`-Yes` salta solo el prompt). Detecta `php.exe` automáticamente (`-PhpExe` /
  `$env:PHP_BIN` / WinGet / PATH) e imprime resumen de conteos al final.
- `database/seeders/MinimalSeeder.php` — solo lo mínimo: settings, cliente GENÉRICO,
  usuarios admin+cajero. **Sin** datos de demo (no corre `ProductSeeder`). El
  `DatabaseSeeder` por defecto (con catálogo de muestra) sigue siendo el de instalación.
- Verificado: tras correrlo → `products=0, invoices=0, supplier*=0`; `customers=1`
  (genérico), `users=2`, `settings=9` (incl. `module_suppliers_enabled=0`). Guarda de
  producción confirmada (sale 2 sin tocar la BD). Documentado en `README-INSTALL.md`.

### Ítems de factura de proveedor — responsive en móvil
- El modal de factura mostraba ~5 campos en una sola fila horizontal: inusable en
  teléfonos. Ahora sigue el patrón del resto del app (escritorio = grilla, móvil =
  tarjeta apilada).
- `partials/_kg-unit-qty.blade.php` se hizo agnóstico al layout con vars Blade
  opcionales `$wrapperClass` (default `col-span-2`) y `$inputClass` (default compacto;
  `form-input` en móvil). **Sin cambios de lógica** (mismos helpers `window.KgGrams`).
- `partials/_supplier-invoice-modal.blade.php`: cada ítem es un bloque `x-for` con dos
  layouts que comparten `row`/`idx`:
  - Escritorio (`hidden sm:grid`): la fila densa de 12 columnas, igual que antes.
  - Móvil (`sm:hidden`): tarjeta con borde redondeado, `bg-gray-50`, espaciado y
    campos etiquetados (Descripción full-width; Unidad+Cantidad y P.unit+Total en
    2 columnas; inputs `form-input` táctiles ~44px; botón "Quitar ✕").
  - KG sigue usando gramos (báscula), UNIT entero, `lineTotal`/totales sin cambios.
- Etiquetas de la lista de proveedores: "+ Nueva Compra" y "Ver Compras".

## Sesión 2026-07-09 — Cotización de productos (preview, copiar, imprimir)

### Nueva funcionalidad: "Generar cotización"
- Nueva pestaña en /products: navegación "Precios | Cotización"
  (`products/_tabs.blade.php`, incluida en ambas vistas; el header anterior del
  index se reemplazó por el partial). Solo admin (mismo grupo de rutas que /products).
- Rutas: `GET /products/cotizacion` y `POST /products/cotizacion/print`
  (registradas ANTES de las rutas `/products/{product}` para evitar colisión).
- `CotizacionController` con DI de constructor (`EscPosTicketRenderer`,
  `ThermalPrinterService`, patrón CarteraController) para poder mockear en tests.
- Es solo informativa: NO crea venta, factura, pago, ni fila en `print_jobs`
  (impresión síncrona directa, como marquillas/cartera).

### UI (`products/cotizacion.blade.php`)
- Selector: búsqueda + filtro por categoría (client-side sobre catálogo embebido,
  sin fetch), checkboxes táctiles 44px, "Seleccionar todos (N)" (respeta filtro),
  "Quitar selección". Productos sin precio: fila atenuada, badge "Sin precio",
  checkbox deshabilitado.
- Vista previa en texto plano (misma agrupación que el ticket), sticky en desktop,
  apilada en móvil (`grid lg:grid-cols-2`).
- "Copiar cotización": `navigator.clipboard` solo en contexto seguro; fallback
  textarea + `execCommand('copy')` **obligatorio** porque la app corre por http://
  en LAN. Banner verde de éxito (patrón marquillas).
- "Imprimir cotización": POST con IDs; el servidor RELEE precios actuales de la BD
  (siempre imprime el último precio guardado) y filtra inactivos/eliminados/sin precio.

### Agrupación por categoría (preview + ticket)
- Secciones por categoría en orden alfabético; productos sin categoría al final
  bajo "Otros"/"OTROS". Misma lógica en Alpine (`groupedSelected`) y en el
  controller (payload `sections`).

### Impresión térmica
- `EscPosTicketRenderer::renderCotizacion(payload)`: logo + header de tienda,
  título "COTIZACION" + fecha/hora, sección PRODUCTOS con headers de categoría en
  negrita, ítems "nombre .... $X.XXX / kg|unidad" (nombres largos: wrap + precio
  alineado a la derecha en línea propia), nota "Precios sujetos a cambios...",
  SIN footer de tienda ("Gracias por su compra" no aplica), largo automático, corte.
- Sanitización ASCII y formato COP con los helpers existentes (`enc`, `cop`).

### Pruebas
- `tests/Feature/CotizacionTest.php` (10, DatabaseTransactions, impresora mockeada):
  auth/admin, render de página, bytes con precios actuales y acentos sanitizados,
  agrupación por categoría (orden alfabético + OTROS al final, cada producto en su
  sección), 422 selección vacía, 422 sin precios válidos, filtra inválidos pero
  imprime válidos, 500 si falla la impresora, y no crea factura/print_job. **10/10 OK**.
- Correr con env overrides de BD dev (phpunit.xml apunta a `mi_pos`).
- Pendiente: prueba física en la XP-80C (espaciado de secciones) y copiar desde
  la tablet por http:// (fallback de portapapeles).

## Sesión 2026-07-13 — Corrección de bugs del instalador Windows

### Revisión completa de `installer/` (proceso de instalación desde 0)
Se auditaron `build-installer.ps1`, `MiPOS.iss` y los scripts de `installer/scripts/`.
Se encontraron y corrigieron 6 bugs (2 bloqueantes que impedían instalar/arrancar):

**Bloqueantes:**
- `install.ps1`: el SQL de creación del rol usaba `\$\$` (escape inválido en
  PowerShell) → psql recibía `DO \$\$` y la creación del usuario de BD fallaba
  siempre, abortando la instalación. Corregido a `` `$`$ `` (genera `$$` válido;
  verificado ejecutando el here-string).
- `start.ps1`: `Start-Process` lanza error si `-RedirectStandardOutput` y
  `-RedirectStandardError` apuntan al mismo archivo → Laravel y el worker nunca
  arrancaban desde el ícono. Ahora logs separados: `laravel-FECHA.log` +
  `laravel-FECHA.err.log` (ídem worker).

**Operación:**
- `stop.ps1`: `artisan serve` lanza un hijo `php -S ... server.php` (el servidor
  real); en Windows matar al padre no mata al hijo → el puerto quedaba ocupado.
  Ahora se barre explícitamente el proceso `server.php`.
- `start.ps1`: si solo el hijo `php -S` estaba vivo, el launcher daba falso
  "puerto en uso por otro proceso". El regex de detección ahora reconoce padre
  e hijo (patrón verificado contra command lines reales).
- `install.ps1`: `pg_isready 2>$null` con `$ErrorActionPreference='Stop'` en
  PS 5.1 podía convertir stderr en excepción fatal → protegido con try/catch.

**Endurecimiento:**
- `MiPOS.iss`: el wizard valida puerto numérico (1–65535) y rechaza comillas
  dobles en impresora/email/contraseña (rompían la línea de comandos de install.ps1).
- `build-installer.ps1`: usa `payload\DonDavid.ico` real (antes generaba stub 1x1
  que ISCC podía rechazar); limpia logs/sesiones/vistas compiladas de dev del
  payload antes de empaquetar.
- `install.ps1` `Set-EnvKey`: escapa `$` en el replacement de regex (contraseñas
  generadas no se corrompen).

### Notas
- `installer/output/` sigue vacío: el `.exe` aún no se ha construido. Próximo
  paso: `.\build-installer.ps1` + prueba end-to-end en VM limpia.
- Actualización de instalaciones existentes: NO usar `git pull` en los PCs
  (C:\MiPOS\app no es repo git, sin vendor/, sin migraciones automáticas).
  Flujo correcto: subir `AppVersion` en MiPOS.iss, rebuild, re-ejecutar el .exe
  (idempotente: preserva .env, BD y admin; aplica migraciones nuevas).
- Se commitea también la spec del agente Roni (parser de pedidos WhatsApp,
  sesión 2026-07-02): `AGENTS.md` + `agents/roni/` (reference, instructions,
  session prompt).

## Sesión 2026-07-24 — Acceso por Tailscale + logo del negocio + copias de conflicto OneDrive

### Commit de esta sesión

| Commit | Descripción |
|--------|------------|
| `0b1c03f` | feat(lan,storage): Tailscale access for cashiers + fix logo URLs |

---

### Contexto: trabajo perdido en copias de conflicto de OneDrive

El repositorio vive dentro de la carpeta sincronizada de OneDrive y se edita desde
dos equipos. OneDrive detectó conflictos y renombró archivos con el sufijo del
equipo (`-DonDavid`), **dejando la versión vieja con el nombre bueno**. Resultado:
había cambios reales fuera del control de versiones que `git status` mostraba
solo como "archivos nuevos sin rastrear", fáciles de confundir con basura.

| Archivo de conflicto | Contenido |
|---|---|
| `EnsureLanAccess-DonDavid.php` | Soporte Tailscale + helper `isInCidr()` — **faltaba en el rastreado** |
| `filesystems-DonDavid.php` | `'serve' => true` movido a disco `public` — **faltaba en el rastreado** |
| `start-mipos-DonDavid.ps1` | Byte-idéntico al bueno — basura |
| `storage/logs/laravel-DonDavid.log` | Log de conflicto (ya ignorado) |

Los dos primeros se portaron a los archivos rastreados (verificado con `diff` =
vacío antes de borrar) y las cuatro copias se eliminaron.

> **Chequeo recomendado antes de cada commit:**
> `git status` buscando `*-DonDavid*` / `*-DESKTOP-*`. La alternativa de fondo es
> sacar el repo de OneDrive y sincronizar entre equipos vía GitHub.

---

### feat: acceso del cajero por Tailscale (rango CGNAT `100.64.0.0/10`)

**Archivo:** `app/Http/Middleware/EnsureLanAccess.php`

**Problema:** `scripts/start-mipos.ps1` levanta el servidor en `0.0.0.0` y anuncia
la IP de Tailscale al arrancar, pero PHP **no** considera `100.64.0.0/10` (CGNAT,
el rango que usa Tailscale) como privado ni reservado. `filter_var` con
`FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE` lo clasificaba como IP
pública → el cajero recibía **403** al entrar por la ruta que el propio script
publica. El admin no se veía afectado (el middleware solo restringe `isCashier()`).

**Fix:** chequeo explícito de CIDR antes del `filter_var`, con helper propio:

```php
// Tailscale's CGNAT range — devices here are already authenticated
// into the tailnet, so treat it as a trusted local network.
if ($this->isInCidr($ip, '100.64.0.0/10')) {
    return true;
}

private function isInCidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = explode('/', $cidr);
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false) {
        return false;   // IPv6 o entrada inválida
    }
    $mask = -1 << (32 - (int) $bits);
    return ($ipLong & $mask) === ($subnetLong & $mask);
}
```

**Implicación de seguridad (decisión consciente):** entrar al tailnet ya exige
autenticación de Tailscale, pero el perímetro se amplía: **cualquier** dispositivo
del tailnet pasa el chequeo del cajero, esté donde esté físicamente. Ya no es
literalmente "solo red local".

---

### fix: `isPrivateIp()` fallaba abierto con IPs inválidas (bug pre-existente)

**Archivo:** `app/Http/Middleware/EnsureLanAccess.php`

**Causa raíz:** `filter_var($ip, FILTER_VALIDATE_IP, NO_PRIV_RANGE|NO_RES_RANGE)`
devuelve `false` en **dos** casos distintos que el código trataba como uno solo:
(a) la IP es privada/reservada, y (b) **la entrada no es una IP válida**. Como la
función retornaba `$isPublic === false`, cualquier basura (`''`, `'no-una-ip'`,
`'999.999.999.999'`) se interpretaba como "es red local" y **permitía** el acceso.

Confirmado contra el código original — no lo introdujo el cambio de Tailscale.
Explotabilidad baja en la práctica (`$request->ip()` normalmente devuelve una IP
válida), pero es un fail-open en un control de acceso.

**Fix — falla cerrado, antes de cualquier otro chequeo:**

```php
private function isPrivateIp(?string $ip): bool
{
    // Fail closed: anything that isn't a valid IP is not the local network.
    if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    ...
```

El parámetro pasó a `?string` porque `Request::ip()` declara `?string` y un `null`
provocaba `TypeError` (500) en lugar de una denegación limpia.

**Verificación — 17/17 casos sobre la clase real vía Reflection:**

| Entrada | Esperado | Motivo |
|---|---|---|
| `127.0.0.1`, `::1` | permite | loopback |
| `192.168.1.50`, `10.0.0.5`, `172.16.3.9` | permite | LAN privada |
| `100.64.0.0` / `100.101.102.103` / `100.127.255.255` | permite | inicio / típico / fin del rango Tailscale |
| `100.63.255.255` | **bloquea** | borde inferior exacto, fuera del rango |
| `100.128.0.0` | **bloquea** | borde superior exacto, fuera del rango |
| `8.8.8.8`, `203.0.113.9` | **bloquea** | IP pública |
| `''`, `no-una-ip`, `evil<script>`, `999.999.999.999`, `null` | **bloquea** | entrada inválida (antes permitía) |

---

### fix: el logo del negocio no cargaba (disco equivocado + symlink)

**Archivos:** `resources/views/layouts/app.blade.php`,
`resources/views/backups/index.blade.php`, `config/filesystems.php`

**Causa A — disco equivocado.** Las vistas usaban `Storage::url($path)`, que
resuelve contra el disco **default** (`local` → `storage/app/private`), pero
`BackupController::uploadLogo()` guarda en el disco **`public`**
(`Storage::disk('public')->put(...)` / `$file->store('logos', 'public')`). La URL
generada apuntaba a una ubicación donde el archivo no existe.

```diff
- <img src="{{ \Illuminate\Support\Facades\Storage::url($__logoPath) }}" ...>
+ <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($__logoPath) }}" ...>
```

**Causa B — dependencia del symlink.** Con el disco correcto, la URL
(`APP_URL/storage/...`) todavía requería el symlink `public/storage`, que se crea
con `php artisan storage:link` — y en Windows **exige permisos de administrador**
o Modo Desarrollador. En una instalación limpia el logo seguiría roto.

**Fix:** mover `'serve' => true` del disco `local` al disco `public`. Laravel
registra entonces una ruta que sirve los archivos del disco directamente, sin
depender del symlink:

```diff
  'local' => [
      'root' => storage_path('app/private'),
-     'serve' => true,
  ],
  'public' => [
      'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
      'visibility' => 'public',
+     'serve' => true,
  ],
```

Verificado que nada más en el proyecto usa `Storage::url` ni `disk('local')`, así
que quitar el flag de `local` no rompe nada. `php artisan config:show` confirma
`public.serve=true` y `local.serve` ausente.

> Este bug **no se manifestaba en la máquina de desarrollo** porque el symlink
> `public/storage` ya existía ahí. Solo aparecería en instalación nueva.

**Causa C — URL absoluta atada a `APP_URL`** (detectada al documentar el cambio,
después del commit `0b1c03f`). Con las causas A y B resueltas, la URL seguía
generándose como `rtrim(env('APP_URL'), '/').'/storage'`, y `.env` tiene
`APP_URL=http://localhost:8000`. Es decir:

```
Storage::disk('public')->url('logos/x.png')
  → http://localhost:8000/storage/logos/x.png
```

Desde un celular por LAN o por Tailscale, `localhost` es **el propio celular** →
imagen rota. El logo solo se veía en el PC del POS, justo el escenario contrario
al que buscaba el cambio de acceso remoto. No hay workaround por configuración:
`APP_URL` no puede ser `localhost` y la IP de Tailscale al mismo tiempo.

**Fix:** URL relativa en el disco `public`.

```diff
- 'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
+ 'url' => '/storage',
```

Verificado que es seguro:
- `php artisan route:list --name=storage` sigue mostrando `storage/{path}` →
  `storage.public`. Laravel registra la ruta con
  `parse_url($config['url'])['path']`, que da `/storage` con URL absoluta o
  relativa indistintamente.
- Los **únicos** dos consumidores son `<img src>` en `layouts/app.blade.php` y
  `backups/index.blade.php`; una URL root-relative funciona desde cualquier host.
- El tiquete térmico **no** se ve afectado: `EscPosTicketRenderer::renderLogo()`
  lee `storage_path('app/public/' . $logoPath)` directamente del disco, sin HTTP.

---

### chore: `scripts/start-mipos.ps1` versionado

Arranque de un solo comando para uso diario (distinto del instalador de
producción, que usa `installer/scripts/start.ps1` + servicios):

1. Verifica e intenta iniciar el servicio `postgresql-x64-16` (avisa sin abortar
   si falta permiso de administrador).
2. `php artisan serve --host=0.0.0.0 --port=8000` en ventana aparte (logs visibles).
3. Espera hasta 20 s a que el puerto responda (`Invoke-WebRequest`).
4. Abre `http://127.0.0.1:8000` en el navegador.
5. Imprime la IP de Tailscale (`tailscale ip -4`) si el CLI está disponible.

Parámetros: `-Port` (default 8000), `-PgServiceName` (default `postgresql-x64-16`).

También se versionó `app/package-lock.json` (`package.json` ya estaba rastreado);
157 paquetes, todos desde `registry.npmjs.org`.

---

### Nota de entorno

El equipo `DonDavid` no tenía identidad de Git configurada. Se definió **solo a
nivel de repositorio** (no global) para coincidir con el historial existente:
`Rodrigo Barrios <rogonec@gmail.com>`.

El push quedó pendiente de autenticación interactiva de GitHub (Git Credential
Manager sin credenciales guardadas en este equipo).

---

## Sesión 2026-07-24 (parte 2) — Precios especiales imprimibles · botón Genérico · productos temporales

Tres mejoras salidas de las pruebas funcionales. Sin migraciones nuevas: el
esquema ya soportaba todo lo necesario.

---

### Mejora 1 — Reporte imprimible de precios especiales (toda la clientela)

**Problema:** los precios especiales se configuran en `Customers/{id}/Edit` pero
no había forma de consultarlos ni de sacarlos en papel.

**Diseño (ajustado tras la primera revisión):** un **único botón en el header de
`/customers`** que imprime un solo tiquete con los precios especiales de **todos**
los clientes, agrupados por cliente. La primera versión traía además una vista de
detalle por cliente (`/customers/{id}/special-prices`) y un enlace por fila; el
dueño la consideró innecesaria y se **eliminó** — el reporte global ya contiene la
sección de cada cliente. Es informativo: no crea venta, factura, pago ni fila en
`print_jobs` (impresión síncrona, como cartera/cotización/marquillas).

| Archivo | Cambio |
|---|---|
| `routes/web.php` | `POST /customers/special-prices/print` (`customers.special-prices.print`), grupo **admin**. Segmento literal, declarada antes de las rutas `/customers/{customer}`. |
| `CustomerController` | Constructor con DI de `EscPosTicketRenderer` + `ThermalPrinterService` (igual que `CarteraController`/`CotizacionController`, para poder mockear en tests). Nuevo método `printSpecialPrices()` — sin parámetros, recorre toda la clientela. |
| `customers/index.blade.php` | Botón "Imprimir precios especiales" junto a "+ Nuevo Cliente"; el `x-data="customerFilter()"` se movió para envolver también el header. Estado `printingPrices`/`printSuccess`/`printError` + método `printSpecialPrices()` (AJAX). Banner verde con el resumen "N precio(s) especial(es) de M cliente(s)". |
| `EscPosTicketRenderer` | Nuevo `renderPreciosEspeciales(payload)` con `sections` (una por cliente). |

**Reglas del reporte:**
- Solo clientes que **tienen** precios especiales (`whereHas('specialPrices.product')`)
  y, dentro de cada uno, solo productos con precio especial — nunca el catálogo completo.
- Excluye productos soft-deleted: la fila de precio sobrevive al borrado del
  producto pero no hay nada que imprimir.
- Precios releídos de la BD en cada impresión (mismo criterio que la cotización).
- Clientes en orden alfabético; productos alfabéticos dentro de cada cliente.
- 422 si ningún cliente tiene precios especiales; 500 si falla la impresora.

**Ticket (`renderPreciosEspeciales`)** — misma estrategia de cuerpo centrado a
54 columnas que factura/cartera/cotización: logo + header de tienda, título
`PRECIOS ESPECIALES` + fecha, cabecera de tabla
`PRODUCTO(23) UN(4) NORMAL(12) ESPECIAL(12)` impresa **una sola vez**, y luego una
sección por cliente (nombre en negrita, razón social indentada debajo si existe,
línea en blanco entre secciones — patrón de `renderCarteraResumen`). Cierra con
`Clientes:` / `Productos:` y la nota. Nombres largos: se envuelven a ancho completo
y las cifras quedan alineadas en la línea siguiente (patrón de `renderCotizacion`).
Sin footer de tienda ("Gracias por su compra" no aplica a un listado de precios),
largo automático.

> **Nota:** `products` no tiene columna de código/SKU, así que el tiquete
> identifica cada producto por nombre.

---

### Mejora 2 — Eliminado el botón "Genérico" de `/sales/new`

`resources/views/sales/create.blade.php`:
- Removido el chip `GENÉRICO` junto al buscador de clientes y la constante JS
  muerta `__genericName`.
- El wrapper `flex gap-2 items-start` (existía solo para acomodar el chip) se
  simplificó a un `div` normal.

**Lo que NO cambió (a propósito):**
- `__genericId` sigue emitiéndose: el `<input type="hidden" name="customer_id">`
  cae en él cuando el cajero no selecciona a nadie — ese sigue siendo el camino
  para facturar al cliente genérico, y ahora es el único.
- El label `(GENÉRICO)` que marca al cliente genérico ya seleccionado se queda.
- `isGenericSelected` sigue en uso (`canSubmit` y el formulario FE en línea):
  el genérico todavía puede elegirse desde el dropdown de búsqueda.

---

### Mejora 3 — Productos temporales dentro de una factura

**Problema:** vender artículos de una sola vez (favores al cliente) obligaba a
crearlos primero en el catálogo.

**Análisis previo:** el backend **ya lo soportaba**. `invoice_items.product_id`
es `nullable` con FK `ON DELETE SET NULL`, la tabla guarda
`product_name_snapshot` + `sale_unit_snapshot`, y `SaleController` ya validaba
`items.*.product_id => ['nullable', 'exists:products,id']`. No hacía falta ni
migración ni una tabla de líneas ad-hoc: una línea temporal es simplemente una
línea con `product_id = null`. Se descartó crear productos "fantasma"
desactivados en `products` (ensuciaría catálogo, búsquedas y cotizaciones).

| Archivo | Cambio |
|---|---|
| `sales/create.blade.php` | Botón `+ Temporal` junto al buscador global; panel ámbar con Descripción · toggle Por unidad/Por kilo · Precio · Cantidad · "Agregar al carrito". Estado `showTempForm`/`tempForm`/`tempError`, computeds `tempKg`/`tempQuantity`/`tempLineTotal`/`tempValid`, métodos `openTempForm`/`cancelTempForm`/`setTempUnit`/`onTempGramsInput`/`addTempItem`. |
| `sales/create.blade.php` | El hidden `items[i][product_id]` se envuelve en `<template x-if="item.product_id">` — se **omite** en líneas temporales para que el servidor reciba `null` y no `''`. Badge "temporal" en el carrito. |
| `sales/create.blade.php` | `addProductItem` no consulta `customPrices` cuando `p.id == null`; `selectCustomer` no re-tarifa líneas temporales (conservan el precio digitado al cambiar de cliente). |
| `SaleController::store()` | Normaliza `product_id` vacío a `null` dentro del recálculo de totales (defensa por si un navegador envía `''`). |
| `SaleService` | Constructor con DI de `EscPosTicketRenderer` + `ThermalPrinterService` (antes `new` directo en `createPrintJob`), para poder mockear la impresora en tests. Se resuelve siempre por contenedor — no existe ningún `new SaleService()`. |

**Comportamiento:**
- La línea temporal es una línea normal: mismo `addProductItem` →
  `computeLineTotal` → subtotal → domicilio → redondeo a 50 → pagos → total, y
  el mismo recálculo con bcmath en el servidor.
- KG en gramos (helpers `window.KgGrams`), UNIT entero — igual que el catálogo.
- El panel queda abierto tras agregar (recuerda la unidad): varias líneas
  temporales seguidas sin reabrir.
- **No** se escribe en `products`, **no** aparece en búsquedas ni cotizaciones,
  y no hay inventario que afectar (el sistema no lo maneja).
- El nombre se imprime en el tiquete vía `product_name_snapshot`.

**Descuentos e impuestos:** la factura no tiene campos de descuento por línea ni
de IVA, así que no aplican a las líneas temporales (ni a las normales). El
precio unitario editable en el carrito cubre el caso de "hacer precio".

---

### Pruebas

| Archivo | Casos |
|---|---|
| `tests/Feature/SpecialPricesReportTest.php` | 10 — auth/admin, el botón aparece en `/customers`, bytes con precios y acentos sanitizados, agrupación por cliente en orden alfabético (cada producto dentro de su sección), exclusión de clientes sin precios especiales, exclusión de productos soft-deleted, 422 cuando nadie tiene precios, 500 impresora caída, no crea factura/print_job. Los casos con conteos exactos limpian primero `customer_product_prices` (`isolate()`) porque corren contra la BD de desarrollo. |
| `tests/Feature/TemporaryInvoiceItemTest.php` | 7 — la página ofrece el formulario temporal y ya no el chip genérico, línea temporal UNIT con `product_id = null` sin crear producto, línea KG con decimales en totales, mezcla catálogo + temporal (subtotal/pagos/estado PAID), `product_id = ''` normalizado a null, nombre obligatorio, nombre impreso en el tiquete. |

**Suite completa: 41/42.** El único fallo es `ExampleTest::the_application_returns_a_successful_response`
(stub de Laravel que pide `/` sin autenticar y recibe 302 → `/login`);
**verificado que también falla en el árbol limpio** — es previo y ajeno a estos cambios.

Correr con la BD de este equipo: `php artisan test` (aquí `.env` y `phpunit.xml`
coinciden en `mi_pos`; en el PC original hay que pasar los overrides de
`don_david`).

---

---

## Sesión 2026-08-07 — Edición inline de facturas (corrección de facturas ya creadas)

Hasta ahora la vista de detalle `invoices/show` era 100 % de solo lectura y no
existía ningún camino de escritura para facturas: los `InvoiceItem` se diseñaron
como *snapshots* inmutables (write-once) y la única corrección prevista era el
flag `voided` (nunca implementado). Se añadió **edición in situ, solo admin**, en
la propia vista de detalle, con **modo edición atómico** (un botón "Editar"
convierte los valores en campos editables, con recálculo en vivo y un único
"Guardar" que persiste todo en una transacción con row-locks).

### Decisiones (acordadas con el dueño)

- **UX:** modo edición atómico (no guardado por campo). Un solo `PUT`, una transacción.
- **Permisos:** solo admin (grupo de rutas `admin`), como la edición inline de productos.
- **Editable:** fecha, líneas (cantidad, precio, cambiar producto vía selector del
  catálogo, agregar/eliminar líneas — incluye temporales `product_id=null`),
  domicilio, notas y **cambiar el cliente**.
- **Total < pagado:** se permite; el excedente se **devuelve al saldo a favor** del
  cliente (`credit_balance`) documentado con un nuevo tipo de `CreditMovement`.

### Cambios

| Archivo | Cambio |
|---|---|
| `2026_08_07_000001_add_refund_type_to_credit_movements.php` | Nueva migración: amplía el CHECK `credit_movements_type_check` a `IN ('APPLIED_TO_INVOICE','REFUND_FROM_EDIT')`. Guardado `!= sqlite`, con `down()` inverso. |
| `app/Support/ProductCatalog.php` | Nuevo helper `ProductCatalog::tree()` con el árbol categoría→productos del picker. Extraído de `SaleController::create` (que ahora lo reutiliza) para no duplicar y alimentar también el editor. |
| `app/Services/SaleService.php` | Nuevo `updateSale(Invoice, data, User)`: en `DB::transaction` con `lockForUpdate()` sobre factura + cliente, recalcula subtotal/total (mismas reglas bcmath + `roundUp50`/FE que `createSale`), reemplaza los ítems (delete + reinsert), reconcilia `paid_amount`/`balance`/`status` y, si el total cae por debajo de lo pagado, refunda el excedente a `credit_balance` con `CreditMovement` type `REFUND_FROM_EDIT`. No reasigna consecutivo ni imprime. |
| `app/Http/Controllers/InvoiceController.php` | `show()` ahora expone `$isAdmin`, `$canEdit` (admin y `fe_status != ISSUED`), `$cats`, `$generic`. Nuevo `update()` (ruta admin): bloquea FE emitida, valida igual que `SaleController::store` (+ `customer_id`), re-valida FE si `requires_fe`, normaliza `product_id` vacío→null, delega en `updateSale`, redirige con flash. |
| `routes/web.php` | `PUT /invoices/{invoice}` → `invoices.update` en el grupo `admin`. |
| `resources/views/invoices/show.blade.php` | Envuelta en `x-data="invoiceEditor()"` (solo cuando `$canEdit`). Botón "✏️ Editar"; el bloque de solo-lectura y las tarjetas de acción (FE/abono/saldo/impresión) se ocultan con `x-show="!editing"`. Componente Alpine con estado de ítems, picker de catálogo, cliente, totales reactivos y refund preview. Seed JSON de la factura + `$cats`. |
| `resources/views/invoices/_editor.blade.php` | Nuevo partial: formulario `@method('PUT')` con fecha, buscador de cliente (`/customers/search`), filas editables (cantidad KG-en-gramos/UNIT, precio, cambiar producto, eliminar), agregar producto/temporal (picker reutilizado de `sales/create`), domicilio, notas, preview de subtotal/total/saldo + aviso de devolución a saldo a favor, y Guardar/Cancelar. |

**Fix post-implementación (`novalidate`):** el `<form>` del editor lleva `novalidate`.
Los inputs-editores visibles de cantidad/precio no tienen `name` (los datos viajan
por los `<input type="hidden" name="items[..]">`) y el editor KG/UNIT inactivo queda
en el DOM oculto (`display:none`). Sin `novalidate`, la validación HTML5 intentaba
validar ese control oculto no enfocable (p. ej. una línea KG con el input UNIT
`min="1" step="1"` conteniendo `1.25`) y **bloqueaba el submit en silencio** con
`An invalid form control with name='' is not focusable`. La validación real es
Alpine (`canSave`) + servidor, así que la nativa no se necesita.

### Efectos secundarios considerados

- **Sin inventario** que ajustar (el sistema no lo maneja). **Sin IVA/descuentos.**
- **Cartera/dashboard/FE/`pendingInvoices`** leen `total`/`balance` en vivo → se
  auto-corrigen. **`reports/payments`** lee montos de `Payment` (intactos).
- El refund baja `paid_amount` por debajo de la suma de `Payment` (espejo de
  `applyCredit`, que lo sube sin fila `Payment`): consistente y auditado por el
  ledger inmutable.
- **FE `ISSUED` = inmutable:** sin botón editar y `update()` lo rechaza.
- **Fecha** solo-admin (toda la edición lo es); mover la fecha reubica la factura
  en los buckets de dashboard/reportes.

### Pruebas

`tests/Feature/InvoiceEditTest.php` — 16 casos (PHPUnit + `DatabaseTransactions`,
fixtures directos `Invoice`/`InvoiceItem`/`Payment` sin tocar impresora): editar
cantidad/precio/fecha, cambiar producto (snapshot), agregar/eliminar línea,
domicilio→total, cambiar cliente, **total<pagado→refund a saldo a favor con
`CreditMovement REFUND_FROM_EDIT`**, recálculo PARCIAL, cantidad/precio/ítems
inválidos (`assertSessionHasErrors`), FE emitida bloqueada, no-admin 403, y
render del editor sin romper la vista de solo-lectura. **16/16 verde.**

**Suite completa: 57 passed, 1 failed** — el único fallo sigue siendo
`ExampleTest` (302→`/login`), previo y ajeno.
