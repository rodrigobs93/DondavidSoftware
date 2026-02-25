<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\SaleService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private SaleService $saleService) {}

    public function index(Request $request)
    {
        $query = Invoice::with('customer', 'createdBy')
            ->where('voided', false)
            ->orderBy('created_at', 'desc');

        if ($request->filled('q')) {
            $query->where('consecutive', 'like', "%{$request->q}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->paginate(20)->withQueryString();
        return view('invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'customer',
            'items',
            'payments.registeredBy',
            'createdBy',
            'printJobs' => fn($q) => $q->latest()->limit(5),
        ]);
        return view('invoices.show', compact('invoice'));
    }

    public function reprint(Invoice $invoice)
    {
        $this->saleService->createPrintJob($invoice);
        return back()->with('success', 'Reimpresión enviada a la cola de impresión.');
    }

    public function feMarkIssued(Request $request, Invoice $invoice)
    {
        $request->validate([
            'fe_reference' => ['required', 'string', 'max:100', 'min:1'],
        ]);

        if ($invoice->fe_status !== 'PENDING') {
            return back()->withErrors(['fe_reference' => 'Esta factura no está pendiente de FE.']);
        }

        $this->saleService->markFeIssued($invoice, $request->fe_reference, auth()->user());
        return back()->with('success', 'Factura electrónica marcada como emitida y ticket reenviado a impresión.');
    }
}
