<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Supplier;
use App\Services\EscPosTicketRenderer;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        private EscPosTicketRenderer  $renderer,
        private ThermalPrinterService $printer,
    ) {}

    /**
     * Suppliers index — each row carries its outstanding debt and credit balance.
     * Supports live search (JSON) by name / tax id.
     */
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = Supplier::query()
            ->withSum(['invoices as pending_balance' => fn($q) => $q->where('voided', false)], 'balance')
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('tax_id', 'ilike', "%{$search}%");
            }))
            ->orderBy('name');

        $toRow = fn(Supplier $s) => [
            'id'             => $s->id,
            'name'           => $s->name,
            'tax_id'         => $s->tax_id,
            'phone'          => $s->phone,
            'contact'        => $s->contact,
            'pending_balance'=> (string) ($s->pending_balance ?? '0.00'),
            'credit_balance' => (string) $s->credit_balance,
        ];

        $suppliers = $query->get();

        if ($request->wantsJson()) {
            return response()->json($suppliers->map($toRow)->values());
        }

        $globalPending = (string) \App\Models\SupplierInvoice::where('voided', false)
            ->where('balance', '>', 0)->sum('balance');
        $globalCredit  = (string) Supplier::where('credit_balance', '>', 0)->sum('credit_balance');

        $initialData = $suppliers->map($toRow)->values();

        return view('suppliers.index', compact('initialData', 'search', 'globalPending', 'globalCredit'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateSupplier($request);
        Supplier::create($data);

        return redirect()->route('suppliers.index')->with('success', 'Proveedor creado.');
    }

    public function show(Supplier $supplier)
    {
        $pending = $supplier->invoices()
            ->where('voided', false)
            ->where('balance', '>', 0)
            ->with('items')
            ->orderBy('invoice_date')->orderBy('id')
            ->get();

        $paid = $supplier->invoices()
            ->where('voided', false)
            ->where('balance', '<=', 0)
            ->with('items')
            ->orderByDesc('invoice_date')->orderByDesc('id')
            ->get();

        $payments = $supplier->payments()
            ->with('invoice:id,invoice_number')
            ->orderByDesc('paid_at')
            ->limit(100)
            ->get();

        $totalDebt = (string) $pending->sum('balance');
        $credit    = (string) $supplier->credit_balance;
        $net       = bcsub($totalDebt, $credit, 2);
        if (bccomp($net, '0', 2) < 0) {
            $net = '0.00';
        }

        return view('suppliers.show', compact(
            'supplier', 'pending', 'paid', 'payments', 'totalDebt', 'credit', 'net'
        ));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validateSupplier($request, true);
        $supplier->update($data);

        return redirect()->route('suppliers.index')->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->invoices()->exists() || $supplier->payments()->exists()) {
            $supplier->delete(); // soft delete — preserves invoice/payment history
            $message = 'Proveedor eliminado. El historial se conserva.';
        } else {
            $supplier->forceDelete();
            $message = 'Proveedor eliminado definitivamente.';
        }

        return redirect()->route('suppliers.index')->with('success', $message);
    }

    /**
     * Print the consolidated supplier statement (80mm thermal), mirroring the
     * cartera "sacar el cobro" flow: synchronous render + send.
     */
    public function printResumen(Supplier $supplier)
    {
        $invoices = $supplier->pendingInvoices()->get();
        $totalDebt = (string) $invoices->sum('balance');
        $credit    = (string) $supplier->credit_balance;
        $net       = bcsub($totalDebt, $credit, 2);
        if (bccomp($net, '0', 2) < 0) {
            $net = '0.00';
        }

        $payload = [
            'shop'          => Setting::shopInfo(),
            'supplier'      => ['name' => $supplier->name],
            'invoices'      => $invoices->map(fn($inv) => [
                'number'  => $inv->invoice_number ?: '—',
                'date'    => $inv->invoice_date->format('d/m/y'),
                'total'   => (string) $inv->total_amount,
                'balance' => (string) $inv->balance,
            ])->values()->all(),
            'totalDebt'     => $totalDebt,
            'creditBalance' => $credit,
            'netAmount'     => $net,
            'printDate'     => now()->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ];

        try {
            $bytes = $this->renderer->renderSupplierConsolidado($payload);
            $this->printer->send($bytes);
        } catch (\Throwable $e) {
            return back()->withErrors(['print' => 'Error al imprimir: ' . $e->getMessage()]);
        }

        return back()->with('success', 'Estado de cuenta impreso.');
    }

    private function validateSupplier(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name'    => ['required', 'string', 'max:150'],
            'tax_id'  => ['nullable', 'string', 'max:30'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'contact' => ['nullable', 'string', 'max:150'],
            'notes'   => ['nullable', 'string'],
        ];
        if ($isUpdate) {
            $rules['active'] = ['boolean'];
        }

        return $request->validate($rules);
    }
}
