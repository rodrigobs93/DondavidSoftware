<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private SaleService $saleService) {}

    public function create()
    {
        $generic = Customer::generic();

        $cats = ProductCategory::where('active', true)
            ->with(['products' => fn($q) => $q->where('active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->values()
            ->map(fn($cat, $i) => [
                'id'         => $cat->id,
                'name'       => $cat->name,
                'colorIndex' => $i % 6,
                'products'   => $cat->products->map(fn($p) => [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'sale_unit'  => $p->sale_unit,
                    'base_price' => (string) $p->base_price,
                ])->values(),
            ]);

        $uncat = Product::where('active', true)->whereNull('category_id')->orderBy('name')->get();
        if ($uncat->isNotEmpty()) {
            $cats->push([
                'id'         => 0,
                'name'       => 'Sin categoría',
                'colorIndex' => $cats->count() % 6,
                'products'   => $uncat->map(fn($p) => [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'sale_unit'  => $p->sale_unit,
                    'base_price' => (string) $p->base_price,
                ])->values(),
            ]);
        }

        $isAdmin = auth()->user()->isAdmin();
        return view('sales.create', compact('generic', 'cats', 'isAdmin'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'          => ['required', 'exists:customers,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['nullable', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:150'],
            'items.*.sale_unit'    => ['required', 'in:KG,UNIT'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'delivery_fee'         => ['nullable', 'numeric', 'min:0'],
            'requires_fe'          => ['boolean'],
            'payments'             => ['nullable', 'array'],
            'payments.*.method'    => ['required', 'in:CASH,CARD,NEQUI,DAVIPLATA,BREB'],
            'payments.*.amount'    => ['required', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string'],
            'invoice_date'         => ['nullable', 'date', 'before_or_equal:today'],
            'submission_key'       => ['nullable', 'string', 'max:64'],
        ], [
            'customer_id.required'          => 'Selecciona un cliente.',
            'customer_id.exists'            => 'El cliente seleccionado no es válido.',
            'items.required'                => 'Agrega al menos un producto.',
            'items.min'                     => 'Agrega al menos un producto.',
            'items.*.product_name.required' => 'Nombre de producto requerido.',
            'items.*.sale_unit.in'          => 'Unidad de venta no válida.',
            'items.*.quantity.min'          => 'La cantidad debe ser mayor a 0.',
            'items.*.unit_price.min'        => 'El precio no puede ser negativo.',
            'payments.*.method.in'          => 'Método de pago no válido.',
            'payments.*.amount.numeric'     => 'El monto del pago debe ser un número.',
            'payments.*.amount.min'         => 'El monto del pago no puede ser negativo.',
            'delivery_fee.min'              => 'El domicilio no puede ser negativo.',
            'invoice_date.date'             => 'La fecha de factura no es válida.',
            'invoice_date.before_or_equal'  => 'La fecha de factura no puede ser futura.',
        ]);

        // Strip invoice_date for non-admins (field is admin-only in the form)
        if (!auth()->user()->isAdmin()) {
            unset($validated['invoice_date']);
        }

        // Strip zero-amount payment rows
        $validated['payments'] = array_values(
            array_filter(
                $validated['payments'] ?? [],
                fn($p) => bccomp((string)($p['amount'] ?? 0), '0', 2) > 0
            )
        );

        // Validate FE constraints
        if (!empty($validated['requires_fe'])) {
            $customer = Customer::find($validated['customer_id']);
            if ($customer->is_generic) {
                return back()
                    ->withErrors(['requires_fe' => 'No se puede emitir FE para el cliente GENÉRICO.'])
                    ->withInput();
            }
            if (!$customer->doc_type || !$customer->doc_number) {
                return back()
                    ->withErrors(['requires_fe' => 'El cliente debe tener tipo y número de documento para FE.'])
                    ->withInput();
            }
        }

        // Backend recompute of totals — never trust JS
        $computedTotal = '0';
        foreach ($validated['items'] as &$item) {
            $lineTotal = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
            $item['line_total'] = $lineTotal;
            $computedTotal = bcadd($computedTotal, $lineTotal, 2);
        }
        unset($item);

        $deliveryFee   = bcadd('0', (string) ($validated['delivery_fee'] ?? '0'), 2);
        $computedTotal = bcadd($computedTotal, $deliveryFee, 2);

        $paidAmount = '0';
        foreach ($validated['payments'] as $p) {
            $paidAmount = bcadd($paidAmount, (string) $p['amount'], 2);
        }

        if (bccomp($paidAmount, $computedTotal, 2) > 0) {
            return back()
                ->withErrors(['payments' => 'El total de pagos no puede superar el total de la factura.'])
                ->withInput();
        }

        $validated['delivery_fee'] = $deliveryFee;

        // Idempotency: if this submission_key already produced an invoice, return it
        if (!empty($validated['submission_key'])) {
            $existing = Invoice::where('submission_key', $validated['submission_key'])->first();
            if ($existing) {
                return redirect()->route('invoices.show', $existing)
                    ->with('success', "Factura #{$existing->consecutive} creada exitosamente.");
            }
        }

        $invoice = $this->saleService->createSale($validated, auth()->user());

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura #{$invoice->consecutive} creada exitosamente.");
    }
}
