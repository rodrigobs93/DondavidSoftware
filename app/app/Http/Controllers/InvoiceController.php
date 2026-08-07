<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\SaleService;
use App\Support\ProductCatalog;
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

        $isAdmin = auth()->user()->isAdmin();
        // Admins get the inline editor, which needs the catalog picker + generic
        // customer fallback (same data the Nueva Venta screen uses). The editor is
        // only rendered for FE-editable invoices; skip the query otherwise.
        $canEdit = $isAdmin && $invoice->fe_status !== 'ISSUED';
        $cats    = $canEdit ? ProductCatalog::tree() : collect();
        $generic = $canEdit ? Customer::generic() : null;

        return view('invoices.show', compact('invoice', 'isAdmin', 'canEdit', 'cats', 'generic'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        // FE-issued invoices are legally immutable.
        if ($invoice->fe_status === 'ISSUED') {
            return back()->withErrors([
                'edit' => 'La factura electrónica ya fue emitida y no puede editarse.',
            ]);
        }

        $validated = $request->validate([
            'customer_id'          => ['required', 'exists:customers,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['nullable', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:150'],
            'items.*.sale_unit'    => ['required', 'in:KG,UNIT'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'delivery_fee'         => ['nullable', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string'],
            'invoice_date'         => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'customer_id.required'          => 'Selecciona un cliente.',
            'customer_id.exists'            => 'El cliente seleccionado no es válido.',
            'items.required'                => 'La factura debe tener al menos un producto.',
            'items.min'                     => 'La factura debe tener al menos un producto.',
            'items.*.product_name.required' => 'Nombre de producto requerido.',
            'items.*.sale_unit.in'          => 'Unidad de venta no válida.',
            'items.*.quantity.min'          => 'La cantidad debe ser mayor a 0.',
            'items.*.unit_price.min'        => 'El precio no puede ser negativo.',
            'delivery_fee.min'              => 'El domicilio no puede ser negativo.',
            'invoice_date.date'             => 'La fecha de factura no es válida.',
            'invoice_date.before_or_equal'  => 'La fecha de factura no puede ser futura.',
        ]);

        // Re-validate FE customer constraints when the invoice requires FE
        // (the flag itself is not editable here; only the customer can change).
        if ($invoice->requires_fe) {
            $customer = Customer::find($validated['customer_id']);
            if ($customer->is_generic) {
                return back()
                    ->withErrors(['customer_id' => 'No se puede emitir FE para el cliente GENÉRICO.'])
                    ->withInput();
            }
            if (!$customer->doc_type || !$customer->doc_number) {
                return back()
                    ->withErrors(['customer_id' => 'El cliente debe tener tipo y número de documento para FE.'])
                    ->withInput();
            }
        }

        // Normalize empty product_id to null so '' never reaches the FK column.
        foreach ($validated['items'] as &$item) {
            $item['product_id'] = ($item['product_id'] ?? null) ?: null;
        }
        unset($item);

        $this->saleService->updateSale($invoice, $validated, auth()->user());

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura #{$invoice->consecutive} actualizada exitosamente.");
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
