<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\SaleService;
use Illuminate\Http\Request;

class CarteraController extends Controller
{
    public function __construct(private SaleService $saleService) {}

    public function index(Request $request)
    {
        $q         = $request->input('q', '');
        $startDate = $request->input('start_date', '');
        $endDate   = $request->input('end_date', '');

        $query = Invoice::with('customer')
            ->where('balance', '>', 0)
            ->where('voided', false)
            ->orderBy('invoice_date', 'asc');

        $query->applyFilters($q, $startDate, $endDate);

        // Total balance is always the global outstanding amount (unaffected by filters)
        $totalBalance = Invoice::where('balance', '>', 0)->where('voided', false)->sum('balance');

        $toRow = fn($inv) => [
            'id'            => $inv->id,
            'consecutive'   => $inv->consecutive,
            'invoice_date'  => $inv->invoice_date->format('d/m/Y'),
            'customer_name' => $inv->customer?->name ?? '—',
            'total'         => (string) $inv->total,
            'paid_amount'   => (string) $inv->paid_amount,
            'balance'       => (string) $inv->balance,
            'status'        => $inv->status,
        ];

        if ($request->wantsJson()) {
            return response()->json($query->get()->map($toRow));
        }

        $invoices    = $query->paginate(20)->withQueryString();
        $initialData = $invoices->map($toRow);

        return view('cartera.index', compact(
            'invoices', 'totalBalance', 'initialData', 'q', 'startDate', 'endDate'
        ));
    }

    public function addPayment(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'method' => ['required', 'in:CASH,CARD,NEQUI,DAVIPLATA,BREB'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes'  => ['nullable', 'string'],
        ]);

        if ($invoice->balance <= 0) {
            return back()->withErrors(['amount' => 'Esta factura ya está pagada.']);
        }

        try {
            $this->saleService->addPayment($invoice, $validated, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Abono registrado exitosamente.');
    }
}
