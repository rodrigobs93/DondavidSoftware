<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CarteraController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FePendingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MarquillaController;
use App\Http\Controllers\QuickSaleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierPaymentController;
use Illuminate\Support\Facades\Route;

// ─── Auth ───────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/forgot-password', [PasswordRecoveryController::class, 'showForm'])
        ->name('password.forgot');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'reset'])
        ->middleware('throttle:10,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Authenticated + LAN check ──────────────────────────────────────────────
Route::middleware(['auth', 'lan'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Sales
    Route::get('/sales/new', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');

    // Marquillas (product labels)
    Route::post('/marquillas/print', [MarquillaController::class, 'print'])->name('marquillas.print');

    // Quick Sales
    Route::post('/quick-sales',                        [QuickSaleController::class, 'store'])->name('quick-sales.store');
    Route::get('/quick-sales/{quickSale}',             [QuickSaleController::class, 'show'])->name('quick-sales.show');
    Route::post('/quick-sales/{quickSale}/print',      [QuickSaleController::class, 'print'])->name('quick-sales.print');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/reprint', [InvoiceController::class, 'reprint'])->name('invoices.reprint');

    // Cartera
    Route::get('/cartera', [CarteraController::class, 'index'])->name('cartera.index');
    // Consolidated customer payment — must be before the /{customer} route to avoid route conflict
    Route::post('/cartera/customers/{customer}/payments', [CarteraController::class, 'addConsolidatedPayment'])->name('cartera.customer.payments');
    // Customer detail — restrict to numeric IDs so 'customers' segment doesn't match
    Route::get('/cartera/{customer}', [CarteraController::class, 'customer'])->name('cartera.customer')->where('customer', '[0-9]+');
    // Invoice-level abono (existing, unchanged)
    Route::post('/cartera/{invoice}/payments', [CarteraController::class, 'addPayment'])->name('cartera.payments')->where('invoice', '[0-9]+');
    // Apply customer credit (saldo a favor) to a specific invoice on demand
    Route::post('/cartera/{invoice}/apply-credit', [CarteraController::class, 'applyCredit'])->name('cartera.invoice.apply-credit')->where('invoice', '[0-9]+');
    // Print "sacar el cobro" thermal summary
    Route::post('/cartera/{customer}/print', [CarteraController::class, 'printResumen'])->name('cartera.customer.print')->where('customer', '[0-9]+');

    // FE Pending
    Route::get('/fe-pending', [FePendingController::class, 'index'])->name('fe-pending.index');

    // Search JSON endpoints (used by Alpine autocomplete)
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');

    // Customer special prices — readable by all authenticated users (cashiers need it during sales)
    Route::get('/customers/{customer}/prices', [CustomerController::class, 'getPrices'])->name('customers.prices');

    // ─── Admin-only ──────────────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {

        // Invoice FE mark issued
        Route::post('/invoices/{invoice}/fe-mark-issued', [InvoiceController::class, 'feMarkIssued'])
            ->name('invoices.fe-mark-issued');

        // Invoice inline edit (admin correction of an existing factura)
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])
            ->name('invoices.update');

        // Products
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        // Cotización — literal segments; must be before /products/{product} routes
        Route::get('/products/cotizacion', [CotizacionController::class, 'index'])->name('products.cotizacion');
        Route::post('/products/cotizacion/print', [CotizacionController::class, 'print'])->name('products.cotizacion.print');
        Route::post('/products/{product}/price', [ProductController::class, 'updatePrice'])->name('products.price');
        Route::post('/products/{product}/name', [ProductController::class, 'updateName'])->name('products.name');
        Route::post('/products/{product}/category', [ProductController::class, 'updateCategory'])->name('products.category');
        Route::post('/products/{product}/toggle', [ProductController::class, 'toggleActive'])->name('products.toggle');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        // Product categories
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::post('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::post('/categories/{category}/toggle', [CategoryController::class, 'toggleActive'])->name('categories.toggle');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Customers
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        // Special prices report — one ticket with every customer's agreed prices.
        // Literal segment; declared before the /customers/{customer} routes.
        Route::post('/customers/special-prices/print', [CustomerController::class, 'printSpecialPrices'])->name('customers.special-prices.print');
        Route::post('/customers/{customer}/prices', [CustomerController::class, 'upsertPrice'])->name('customers.prices.upsert');
        Route::patch('/customers/{customer}/prices/{product}', [CustomerController::class, 'updatePrice'])->name('customers.prices.update');
        Route::delete('/customers/{customer}/prices/{product}', [CustomerController::class, 'deletePrice'])->name('customers.prices.delete');

        // Reports
        Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
        Route::patch('/payments/{payment}/verify', [ReportController::class, 'verifyPayment'])->name('payments.verify');
        Route::patch('/customer-payments/{customerPayment}/verify', [ReportController::class, 'verifyCustomerPayment'])->name('customer-payments.verify');
        Route::post('/payments/verify-bulk', [ReportController::class, 'verifyBulk'])->name('payments.verify-bulk');

        // Backups & Settings
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/export', [BackupController::class, 'export'])->name('backups.export');
        Route::post('/backups/settings', [BackupController::class, 'saveSettings'])->name('backups.settings');
        Route::post('/backups/test-print', [BackupController::class, 'testPrint'])->name('backups.test-print');
        Route::post('/backups/logo', [BackupController::class, 'uploadLogo'])->name('backups.logo.upload');
        Route::delete('/backups/logo', [BackupController::class, 'deleteLogo'])->name('backups.logo.delete');

        // ─── Suppliers / Cuentas por Pagar (module-gated) ──────────────────────
        Route::middleware('suppliers')->group(function () {
            Route::get('/suppliers',                 [SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('/suppliers/create',          [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('/suppliers',                [SupplierController::class, 'store'])->name('suppliers.store');
            Route::get('/suppliers/{supplier}',      [SupplierController::class, 'show'])->name('suppliers.show')->where('supplier', '[0-9]+');
            Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit')->where('supplier', '[0-9]+');
            Route::put('/suppliers/{supplier}',      [SupplierController::class, 'update'])->name('suppliers.update')->where('supplier', '[0-9]+');
            Route::delete('/suppliers/{supplier}',   [SupplierController::class, 'destroy'])->name('suppliers.destroy')->where('supplier', '[0-9]+');

            Route::post('/suppliers/{supplier}/invoices', [SupplierInvoiceController::class, 'store'])->name('suppliers.invoices.store')->where('supplier', '[0-9]+');
            Route::post('/suppliers/{supplier}/payments', [SupplierPaymentController::class, 'storeConsolidated'])->name('suppliers.payments')->where('supplier', '[0-9]+');
            Route::post('/suppliers/{supplier}/print',    [SupplierController::class, 'printResumen'])->name('suppliers.print')->where('supplier', '[0-9]+');
            Route::post('/supplier-invoices/{supplierInvoice}/payments', [SupplierPaymentController::class, 'storeInvoicePayment'])->name('supplier-invoices.payments')->where('supplierInvoice', '[0-9]+');
        });
    });
});
