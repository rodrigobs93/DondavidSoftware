<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private SaleService $saleService) {}

    public function create()
    {
        $generic = Customer::generic();
        return view('sales.create', compact('generic'));
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
            'payments'             => ['required', 'array', 'min:1'],
            'payments.*.method'    => ['required', 'in:CASH,CARD,NEQUI,DAVIPLATA,BREB'],
            'payments.*.amount'    => ['required', 'numeric', 'min:0.01'],
            'notes'                => ['nullable', 'string'],
        ]);

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
        $invoice = $this->saleService->createSale($validated, auth()->user());

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura #{$invoice->consecutive} creada exitosamente.");
    }
}
