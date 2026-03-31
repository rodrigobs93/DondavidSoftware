<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\CustomerPaymentService;
use App\Services\EscPosTicketRenderer;
use App\Services\SaleService;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;

class CarteraController extends Controller
{
    public function __construct(
        private SaleService            $saleService,
        private CustomerPaymentService $customerPaymentService,
        private EscPosTicketRenderer   $renderer,
        private ThermalPrinterService  $printer,
    ) {}

    /**
     * Cartera index — grouped by customer.
     *
     * Returns JSON: { customers: [...], global_total_balance }
     * Each customer entry: { customer: {id, name, business_name, credit_balance},
     *                        invoice_count, total_balance }
     */
    public function index(Request $request)
    {
        $q         = trim($request->input('q', ''));
        $startDate = $request->input('start_date', '');
        $endDate   = $request->input('end_date', '');

        // Global total: all outstanding, unaffected by filters.
        $globalTotalBalance = (string) Invoice::where('balance', '>', 0)
                                               ->where('voided', false)
                                               ->sum('balance');

        // Build filtered query for grouping.
        $query = Invoice::with(['customer' => fn($q) => $q->withTrashed()])
            ->where('balance', '>', 0)
            ->where('voided', false);

        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }

        // Filter by customer name / business_name.
        if ($q !== '') {
            $query->whereHas('customer', function ($cq) use ($q) {
                $cq->withTrashed()
                   ->where('name', 'ilike', "%{$q}%")
                   ->orWhere('business_name', 'ilike', "%{$q}%");
            });
        }

        $invoices = $query->orderBy('invoice_date', 'asc')->get();

        // Group by customer_id in PHP (avoids complex GROUP BY with eager loading).
        $grouped = $invoices->groupBy('customer_id')
            ->map(function ($group) {
                $customer = $group->first()->customer;
                return [
                    'customer' => [
                        'id'             => $customer?->id,
                        'name'           => $customer?->name ?? '—',
                        'business_name'  => $customer?->business_name ?? '',
                        'credit_balance' => (string) ($customer?->credit_balance ?? '0.00'),
                    ],
                    'invoice_count' => $group->count(),
                    'total_balance' => (string) $group->sum('balance'),
                ];
            })
            ->values()
            ->sortByDesc('total_balance')
            ->values();

        // Saldo a favor: customers with credit_balance > 0, optionally filtered by name.
        $saldoQuery = Customer::where('credit_balance', '>', 0)->withTrashed(false);
        if ($q !== '') {
            $saldoQuery->where(function ($cq) use ($q) {
                $cq->where('name', 'ilike', "%{$q}%")
                   ->orWhere('business_name', 'ilike', "%{$q}%");
            });
        }
        $saldoCustomers = $saldoQuery
            ->orderByDesc('credit_balance')
            ->get()
            ->map(fn($c) => [
                'customer' => [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'business_name'  => $c->business_name ?? '',
                    'credit_balance' => (string) $c->credit_balance,
                ],
            ])
            ->values();

        $globalSaldoBalance = (string) Customer::where('credit_balance', '>', 0)->sum('credit_balance');

        if ($request->wantsJson()) {
            return response()->json([
                'customers'             => $grouped,
                'global_total_balance'  => $globalTotalBalance,
                'saldo_customers'       => $saldoCustomers,
                'global_saldo_balance'  => $globalSaldoBalance,
            ]);
        }

        return view('cartera.index', [
            'initialData'         => $grouped,
            'globalTotalBalance'  => $globalTotalBalance,
            'initialSaldo'        => $saldoCustomers,
            'globalSaldoBalance'  => $globalSaldoBalance,
            'q'                   => $q,
            'startDate'           => $startDate,
            'endDate'             => $endDate,
        ]);
    }

    /**
     * Customer cartera detail page.
     */
    public function customer(Customer $customer, Request $request)
    {
        $group    = $request->input('group', 'none'); // none | day | week
        $invoices = $customer->pendingInvoices()->get();
        $totalDebt = (string) $invoices->sum('balance');

        // Week grouping uses Sunday as week start (Colombia convention).
        // Key = the Sunday date that opens the week (Y-m-d), so every invoice in the
        // same Sun–Sat span gets the same key regardless of ISO year-week quirks.
        $groupedInvoices = match ($group) {
            'day'  => $invoices->groupBy(fn($inv) => $inv->invoice_date->format('Y-m-d')),
            'week' => $invoices->groupBy(
                fn($inv) => $inv->invoice_date->copy()->startOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d')
            ),
            default => null,
        };

        // Build a flat array for Alpine.js (JSON-serialisable).
        $invoicesJson = $invoices->map(fn($inv) => [
            'id'          => $inv->id,
            'consecutive' => $inv->consecutive,
            'date'        => $inv->invoice_date->format('d/m/Y'),
            'total'       => (int) round((float) $inv->total),
            'paid_amount' => (int) round((float) $inv->paid_amount),
            'balance'     => (int) round((float) $inv->balance),
            // week-Sunday key for client-side grouping
            'week_key'    => $inv->invoice_date->copy()->startOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d'),
            'day_key'     => $inv->invoice_date->format('Y-m-d'),
        ])->values()->all();

        return view('cartera.customer', [
            'customer'        => $customer,
            'invoices'        => $invoices,
            'invoicesJson'    => $invoicesJson,
            'groupedInvoices' => $groupedInvoices,
            'group'           => $group,
            'totalDebt'       => $totalDebt,
            'paymentMethods'  => Payment::$methods,
        ]);
    }

    /**
     * Print "sacar el cobro" thermal summary for a customer.
     * Accepts ?group=none|day|week to mirror the UI grouping.
     */
    public function printResumen(Customer $customer, Request $request)
    {
        $group    = $request->input('group', 'none');
        $invoices = $customer->pendingInvoices()->get();
        $totalDebt = (string) $invoices->sum('balance');
        $creditBal = (string) $customer->credit_balance;
        $netAmount = bcsub($totalDebt, $creditBal, 2);

        $shop = Setting::shopInfo();

        $toRow = fn($inv) => [
            'consecutive' => $inv->consecutive,
            'date'        => $inv->invoice_date->format('d/m/y'),
            'total'       => (string) $inv->total,
            'balance'     => (string) $inv->balance,
        ];

        // Build sections matching the UI grouping.
        if ($group === 'day') {
            $sections = $invoices
                ->groupBy(fn($inv) => $inv->invoice_date->format('Y-m-d'))
                ->map(fn($group, $key) => [
                    'label'    => \Carbon\Carbon::parse($key)->format('d/m/Y'),
                    'invoices' => $group->map($toRow)->values()->all(),
                ])->values()->all();
        } elseif ($group === 'week') {
            $sections = $invoices
                ->groupBy(fn($inv) => $inv->invoice_date->copy()->startOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d'))
                ->map(fn($group, $key) => [
                    'label'    => \Carbon\Carbon::parse($key)->format('d/m/Y')
                                . ' - '
                                . \Carbon\Carbon::parse($key)->addDays(6)->format('d/m/Y'),
                    'invoices' => $group->map($toRow)->values()->all(),
                ])->values()->all();
        } else {
            $sections = null; // flat list
        }

        $payload = [
            'shop'          => $shop,
            'customer'      => [
                'name'          => $customer->name,
                'business_name' => $customer->business_name ?? '',
            ],
            'invoices'      => $invoices->map($toRow)->values()->all(),
            'sections'      => $sections,
            'totalDebt'     => $totalDebt,
            'creditBalance' => $creditBal,
            'netAmount'     => bccomp($netAmount, '0', 2) < 0 ? '0' : $netAmount,
            'printDate'     => now()->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ];

        try {
            $bytes = $this->renderer->renderCarteraResumen($payload);
            $this->printer->send($bytes);
        } catch (\Throwable $e) {
            return back()->withErrors(['print' => 'Error al imprimir: ' . $e->getMessage()]);
        }

        return back()->with('success', 'Cobro impreso.');
    }

    /**
     * Register a consolidated payment for a customer (FIFO allocation).
     */
    public function addConsolidatedPayment(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'method' => ['required', 'in:CASH,CARD,NEQUI,DAVIPLATA,BREB'],
            'amount' => ['required', 'integer', 'min:1'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->customerPaymentService->applyConsolidatedPayment(
                customer: $customer,
                amount:   (string) $validated['amount'],
                method:   $validated['method'],
                notes:    $validated['notes'] ?? null,
                user:     auth()->user()
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => 'Error al registrar el pago: ' . $e->getMessage()]);
        }

        $msg = 'Pago consolidado registrado.';

        if (bccomp($result['allocated'], '0', 2) > 0) {
            $msg .= ' Distribuido: $' . number_format((float) $result['allocated'], 0, ',', '.');
        }
        if (bccomp($result['credit_added'], '0', 2) > 0) {
            $msg .= ' — Saldo a favor: $' . number_format((float) $result['credit_added'], 0, ',', '.');
        }
        if ($result['invoices_fully_paid'] > 0) {
            $msg .= " ({$result['invoices_fully_paid']} factura(s) pagada(s) completamente).";
        }

        return redirect()->route('cartera.customer', $customer)->with('success', $msg);
    }

    /**
     * Add a payment to a specific invoice (existing invoice-level abono — unchanged).
     */
    public function addPayment(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'method' => ['required', 'in:CASH,CARD,NEQUI,DAVIPLATA,BREB'],
            'amount' => ['required', 'integer', 'min:1'],
            'notes'  => ['nullable', 'string'],
        ]);

        if ($invoice->balance <= 0) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Esta factura ya está pagada.'], 422);
            }
            return back()->withErrors(['amount' => 'Esta factura ya está pagada.']);
        }

        try {
            $this->saleService->addPayment($invoice, $validated, auth()->user());
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            $invoice->refresh();
            return response()->json([
                'ok'          => true,
                'balance'     => (int) round((float) $invoice->balance),
                'paid_amount' => (int) round((float) $invoice->paid_amount),
            ]);
        }

        return back()->with('success', 'Abono registrado exitosamente.');
    }
}
