# Don David Software — Project Context

**Last updated:** 2026-03-30
**Business:** Carnicería Don David · Plaza de Paloquemao · Bogotá, Colombia
**Status:** MVP functional

---

## 1. Business Overview

- Butcher shop selling beef and pork at Paloquemao market + home deliveries.
- Sales channels: in-person and WhatsApp/phone orders.
- Payment methods accepted: Cash, Card, Nequi, Daviplata, Bre-B (bank transfer). Non-cash payments require verification in the corresponding app.
- Electronic invoicing (FE): only on customer request, submitted manually via the DIAN portal. DIAN API integration is out of scope.
- Accounts receivable (cartera): frequent customers (restaurants) may pay partially or later. The system supports partial payments against specific invoices.
- No inventory management — out of scope.

---

## 2. Tech Stack & Architecture

### Stack
- **Backend:** Laravel (PHP 8.2) + Blade templates + Alpine.js (CDN) + Tailwind CSS (CDN)
- **Database:** PostgreSQL 16 — local on the POS PC
- **No build step** — CDN only (no Vite/npm)

### Access Model
- App runs on the **POS PC** (`php artisan serve --host=0.0.0.0 --port=8000`).
- Accessed via **localhost** on the POS PC and via **LAN** from phones/tablets on the same Wi-Fi.
- **Admin** role: can access from outside LAN (future cloud deployment planned).
- **Cashier** role: restricted to LAN-only by `EnsureLanAccess` middleware.

### Printing
- Thermal printer: **XP-80C** via **USB001** (Windows spooler), 80mm roll.
- Print method: `ThermalPrinterService` — writes ESC/POS bytes to a temp `.bin` file, then sends via PowerShell + `winspool.drv` P/Invoke (`OpenPrinter / StartDocPrinter / WritePrinter`, datatype=RAW).
- Printing is **synchronous** — happens inside the HTTP request. No worker daemon required.
- `PrintJob` records are still created for history/diagnostics.
- `app:print-worker` artisan command exists but is not needed for day-to-day printing.

---

## 3. Environment Setup

| Item | Detail |
|------|--------|
| PHP | 8.2 via WinGet — NOT in system PATH |
| Full PHP path | `C:\Users\rodri\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_...` |
| Database | PostgreSQL 16 · DB: `don_david` · user: `don_david_user` · pass: `don_david_pass` |
| App start | `php artisan serve --host=0.0.0.0 --port=8000` |
| Repo | `https://github.com/rodrigobs93/DondavidSoftware.git` · branch: `main` |
| Working dir | `c:\Users\rodri\OneDrive\COLDEVS\donDavidSoftware` |
| Laravel app | `donDavidSoftware/app/` |

### Known infrastructure gaps (not yet fixed)
- P3: PHP not in system PATH (must use full path)
- P4: No NSSM/Task Scheduler service for the app — runs manually
- P5: Port 8000 not open in Windows Firewall
- P6: No static IP assigned to POS PC

---

## 4. Database & Models

- 17+ migrations applied ✓ · 4 seeders run ✓
- Models: `Invoice`, `InvoiceItem`, `Customer`, `Product`, `Category`, `Payment`, `User`, `Setting`, `PrintJob`, `QuickSale`
- Key services: `SaleService`, `QuickSaleService`, `EscPosTicketRenderer`, `ThermalPrinterService`
- Helpers: `app/helpers.php` — `format_cop()` (PHP COP formatter), autoloaded via `composer.json`
- Global JS: `window.formatCOP`, `window.formatGrams`

---

## 5. Modules

### Sales — `/sales/new`
- Customer search by name and `business_name` (restaurant/company name).
- Products sold as **KG** or **UNIT** only (never both for the same product).
  - KG: input in grams with thousand-separator display (e.g. `1.000` = 1000 g), stored/calculated in kg.
  - UNIT: integer only.
- Category chips for product filtering.
- Quantity panel with KG/unit toggle.
- Optional delivery fee and notes.
- Mixed payments: CASH, CARD, NEQUI, DAVIPLATA, BREB (payment chips UI).
- Invoice states set automatically: **PAID** (balance=0), **PARTIAL** (partial payment), **PENDING** (no payment).
- Double-submit prevention via `submission_key` UUID (unique DB constraint).
- Customer input starts blank — falls back to GENERIC customer on submit if no selection.

### Invoices — `/invoices`
- List: `#Invoice · Date · Customer · Company · Total · Status`
  - Status badge in Spanish: Pagada / Parcial / Pendiente
  - Live search + date range + status filter (Alpine.js, no page reload)
- Detail: reprint, add payment (abono), FE control.

### Cartera (Accounts Receivable)
- Lists invoices with balance > 0.
- Same live search + date filter UX as invoices.
- Register partial payments (abonos) against a specific invoice.

### FE (Electronic Invoice Queue)
- FE is manual (DIAN portal), but the system tracks: **PENDING** / **ISSUED + reference (CUFE/No.)**.
- List order: PENDING first, ISSUED last, then by date DESC.
- Marking as ISSUED does **not** trigger a reprint.

### Products
- CRUD: name, `sale_unit` (KG/UNIT), base price, active flag.
- Inline price and name editing.
- Soft delete if product is referenced in invoices; hard delete otherwise.
- Dynamic categories: `product_categories` table, category CRUD, filter chips by category.
- Admin-only: price edits tracked with `price_updated_at` + `price_updated_by_user_id`.

### Customers
- Fields: name, `doc_type` (NIT/CC), `doc_number`, phone, email, address, `business_name` (required when doc_type = NIT).
- Special prices per customer/product: created/edited/deleted in the customer detail view; applied automatically on sale by `customer_id`.
- Soft delete if customer has invoices; hard delete otherwise.
- Special **GENERIC** customer for fast sales without data entry (blocked when FE is required).

### Payment Verification — `/reports/payments`
- Reconciliation of non-cash payments (CASH excluded by default).
- Filters: date range, search, "unverified only".
- Columns: `#Invoice · Customer · Company · Method · Amount · Payment Date · Verified`.
- Mark individual or bulk as verified.
- Non-cash quick sale payments also appear here.

### Quick Sale — `R-XXXXXX`
- Modal in nav bar, dispatched via `open-quick-sale` event.
- Own consecutive sequence (`receipt_consecutive_seq` PostgreSQL sequence → `R-000001` format).
- Total + payment method; if cash: prompts amount and calculates change.
- Optional print (default: NO).
- `submission_key` idempotency.
- Receipt detail view at `quick-sales/show`.

### Marquillas (Product Labels)
- Modal in nav bar, dispatched via `open-marquillas` event; purple accent.
- Product search (`/products/search?q=`, 250 ms debounce) + custom text rows.
- Each row: text + copies (1–20). Max 50 rows.
- Live total-copies counter in print button label.
- Auto-closes and resets on success; shows error without closing on failure.
- Double-submit prevention.

### Backups / Config — `/backups`
- Manual backup export (configurable path, e.g. OneDrive).
- Branding:
  - Logo upload: JPG/PNG/WEBP/GIF/SVG, max 2 MB. SVG sanitized (strips `<script>`, `on*` attrs, `javascript:` URIs).
  - Header color: 10 predefined dark swatches with Alpine.js live preview. Stored as `header_color` in `settings` table. Default: `#111827`.
- Test Print button: `POST /backups/test-print` — shows green/red result via Alpine AJAX.

---

## 6. Ticket Printing (80mm)

### Encoding
- ASCII sanitization via `sanitizeForPrinter()` (`strtr()` map + `preg_replace` for non-ASCII).
- Replaces accented chars (á→a, ñ→n, etc.), removes ¡¿.
- No code page commands sent to printer (avoids GBK/Chinese character issue on XP-80C).

### Invoice Ticket (`render()`)
- Logo: raster images (PNG/JPEG/WEBP/GIF) loaded via PHP GD, resized to 384 px wide, converted to 1-bit monochrome, sent via `GS v 0`. Centered by prepending `0x00` bytes (96 white dots per side on 576-dot paper). SVG silently skipped.
- Fonts: shop name + totals = Font A (bold); address, items, payments, footer = Font B (~56 cols).
- Item columns (WIDTH_B=56): `DESCRIPCION(20) CANT(9) P.UNIT(12) TOTAL(12)` + separators.
- Name truncated to 17 chars + `...` if > 20 chars.
- Totals, payments, paid, balance: Font B, full WIDTH_B=56.
- FE status line removed from ticket; customer doc/name still prints for FE invoices.
- Minimum length: `MIN_INVOICE_LINES=46` (~16 cm at 3.5 mm/line) before CUT. Footer placed near tear edge; safety pad ensures total ≥ minimum.

### Quick Sale Ticket (`renderQuickSale()`)
- Logo printed (same as invoice).
- No "VENTA RAPIDA" title.
- Address word-wrapped same as invoice.
- Automatic length (no minimum).

### Marquilla Ticket (`renderMarquilla()`)
- Larger logo: 480-dot target width, 240-dot max height.
- Centered bold large product text.
- Minimum `MIN_MARQUILLA_LINES=14` (~5 cm) + CUT.
- Multiple copies sent in a single `ThermalPrinterService::send()` call.

---

## 7. UI / UX Conventions

- **Currency (COP):** always `$1.000` ($ sign, thousands separated by dot, no decimals in UI).
- **Weight:** input in grams with thousands display; stored and calculated in kg.
- **Responsive layout:**
  - `< 640 px`: mobile card layout (`.pos-card` CSS class)
  - `≥ 640 px`: desktop table layout (`.pos-table` CSS class)
  - `< 1024 px`: hamburger menu replaces flat nav row
    - Backdrop closes menu on tap; ESC key also closes.
    - Touch targets: ~48 px tall (`py-3 px-5`).
    - Inherits `header_color` setting.
- **Touch-first design (2026-03-30):** All key tap targets enforced at min ~44 px.
  - `.pos-btn`: `py-2.5` globally; `.form-input`: `text-base py-2.5`.
  - `.sales-screen input/select/textarea`: `min-height: 44px` via CSS.
  - `.pos-card-label`: `text-sm` (was `text-xs`) for readability.
  - Payment chips, category chips, filter chips all at `py-2.5`/`py-2 text-sm` minimum.
  - Finalize button: `py-4 text-lg`; TOTAL display: `text-xl`/`text-2xl`.
  - Remove buttons (cart ×, payment ×): `p-2 min-w-9 min-h-9`.
- **Role-based UI:** Admin-only actions (products, customers, backups) hidden/disabled for Cashier.
- **Nav logo:** `h-10` height. Falls back to shop name text if no logo set.

---

## 8. Pending / Risks

| Priority | Item |
|----------|------|
| High | Print Worker as Windows service (NSSM / Task Scheduler) so it survives reboots without opening a terminal |
| High | Port 8000 open in Windows Firewall for LAN access |
| Medium | Static IP for POS PC |
| Medium | Backup restore validation |
| Medium | SVG logos not printed (GD limitation) — cosmetic only |
| Low | Future DIAN API integration for FE |
| Low | Cloud deployment (admin external access) with local print bridge |

---

## 9. Development History (Key Milestones)

| Date | What was built |
|------|---------------|
| Pre-2026-02-27 | Laravel scaffold, PostgreSQL, 17 migrations, core models, ESC/POS renderer, `SaleService`, middleware |
| 2026-02-25 | `mb_str_pad` fix (PHP 8.2 compat), inline product name editing, `business_name` on customers, special prices |
| 2026-02-27 | Dynamic categories, customer live search + soft delete, invoice live search + filters |
| 2026-03-03 | Cartera + FE screens, payment verification report, KG grams UX, PENDING/PARTIAL invoice saves |
| 2026-03-04/05 | Nueva Venta redesign (category chips, qty panel, payment chips), duplicate prevention (`submission_key`), Quick Sale modal + R-XXXXXX sequence |
| 2026-03-06 | Windows spooler printing (`ThermalPrinterService` via PowerShell + winspool.drv), synchronous print flow, Test Print button |
| 2026-03-07 | CP850 encoding attempt (later replaced) |
| 2026-03-09 | ASCII sanitization (`strtr` map) — definitive encoding fix |
| 2026-03-11 | Responsive tables (mobile cards), FE inline customer creation, `format_cop()` PHP helper, `formatCOP`/`formatGrams` JS globals, logo upload (initial) |
| 2026-03-16 | SVG logo support (2 MB, XSS-sanitized), configurable header color (10 swatches, live preview), hamburger menu (`< 1024 px`), ticket printing overhaul (logo bitmap, Font B body, unit price column, 16 cm minimum, footer placement, column widths) |
| 2026-03-25 | Marquillas modal + `renderMarquilla()`, ticket fixes (logo on quick sale, word-wrap, column widths, totals WIDTH_B), FE auto-print bug removed, FE list ordering (PENDING first), invoices list cleanup (removed Saldo/FE columns, added Company column) |
| 2026-03-30 | Touch-first UI audit + optimization — min 44px tap targets across all views; `/sales/new` priority (payment chips, qty panel, finalize button, TOTAL hierarchy, remove buttons); filter chips in invoices/customers/reports |
