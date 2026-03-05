<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CarteraController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FePendingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuickSaleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

// ─── Auth ───────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Authenticated + LAN check ──────────────────────────────────────────────
Route::middleware(['auth', 'lan'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Sales
    Route::get('/sales/new', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');

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
    Route::post('/cartera/{invoice}/payments', [CarteraController::class, 'addPayment'])->name('cartera.payments');

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

        // Products
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
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
        Route::post('/customers/{customer}/prices', [CustomerController::class, 'upsertPrice'])->name('customers.prices.upsert');
        Route::delete('/customers/{customer}/prices/{product}', [CustomerController::class, 'deletePrice'])->name('customers.prices.delete');

        // Reports
        Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
        Route::patch('/payments/{payment}/verify', [ReportController::class, 'verifyPayment'])->name('payments.verify');
        Route::post('/payments/verify-bulk', [ReportController::class, 'verifyBulk'])->name('payments.verify-bulk');

        // Backups & Settings
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups/export', [BackupController::class, 'export'])->name('backups.export');
        Route::post('/backups/settings', [BackupController::class, 'saveSettings'])->name('backups.settings');
    });
});
