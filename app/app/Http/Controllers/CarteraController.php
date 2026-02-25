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
        $query = Invoice::with('customer')
            ->where('balance', '>', 0)
            ->where('voided', false)
            ->orderBy('invoice_date', 'asc');

        if ($request->filled('customer')) {
            $query->whereHas('customer', fn($q) =>
                $q->where('name', 'ilike', "%{$request->customer}%")
            );
        }

        $invoices     = $query->paginate(20)->withQueryString();
        $totalBalance = Invoice::where('balance', '>', 0)->where('voided', false)->sum('balance');

        return view('cartera.index', compact('invoices', 'totalBalance'));
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
