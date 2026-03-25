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
        $q         = $request->input('q', '');
        $status    = $request->input('status', '');
        $startDate = $request->input('start_date', '');
        $endDate   = $request->input('end_date', '');

        $query = Invoice::with('customer')
            ->where('voided', false)
            ->orderBy('created_at', 'desc');

        $query->applyFilters($q, $startDate, $endDate);

        if ($status) {
            $query->where('status', $status);
        }

        $toRow = fn($inv) => [
            'id'                    => $inv->id,
            'consecutive'           => $inv->consecutive,
            'invoice_date'          => $inv->invoice_date->format('d/m/Y'),
            'customer_name'         => $inv->customer?->name ?? '—',
            'customer_business_name'=> $inv->customer?->business_name ?? '',
            'total'                 => (string) $inv->total,
            'status'                => $inv->status,
        ];

        if ($request->wantsJson()) {
            return response()->json($query->get()->map($toRow));
        }

        $invoices    = $query->paginate(20)->withQueryString();
        $initialData = $invoices->map($toRow);

        return view('invoices.index', compact(
            'invoices', 'initialData', 'q', 'status', 'startDate', 'endDate'
        ));
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
        $job = $this->saleService->createPrintJob($invoice);
        if ($job->status === 'FAILED') {
            return back()->with('error', 'Error al imprimir: ' . $job->error_message);
        }
        return back()->with('success', 'Ticket impreso correctamente.');
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
        return back()->with('success', 'Factura electrónica marcada como emitida.');
    }
}
