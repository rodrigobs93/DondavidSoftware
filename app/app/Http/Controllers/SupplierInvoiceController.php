<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceController extends Controller
{
    /**
     * Register a new supplier invoice. Line items are optional; when present the
     * total is the sum of their line totals, otherwise the typed total is used.
     */
    public function store(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'invoice_number'      => ['nullable', 'string', 'max:50'],
            'invoice_date'        => ['required', 'date'],
            'due_date'            => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total_amount'        => ['nullable', 'integer', 'min:1'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'items'               => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:150'],
            'items.*.sale_unit'   => ['required_with:items', 'in:KG,UNIT'],
            'items.*.quantity'    => ['required_with:items', 'numeric', 'min:0.001'],
            'items.*.unit_price'  => ['required_with:items', 'integer', 'min:0'],
        ]);

        $items = collect($validated['items'] ?? [])
            ->filter(fn($it) => !empty($it['description']))
            ->values();

        // Compute total from line items when present; else use the typed total.
        if ($items->isNotEmpty()) {
            $total = '0';
            $items = $items->map(function ($it) use (&$total) {
                // UNIT quantities are whole units; KG keeps up to 3 decimals.
                $qty = $it['sale_unit'] === 'UNIT'
                    ? (string) (int) round((float) $it['quantity'])
                    : bcadd('0', (string) $it['quantity'], 3);
                // line_total = quantity * unit_price, rounded to integer COP.
                $lineTotal = (string) (int) round((float) $qty * (float) $it['unit_price']);
                $total = bcadd($total, $lineTotal, 0);
                $it['quantity']   = $qty;
                $it['line_total'] = $lineTotal;
                return $it;
            });
        } else {
            if (empty($validated['total_amount'])) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Indique el total de la factura o agregue ítems.',
                ]);
            }
            $total = (string) $validated['total_amount'];
        }

        $invoice = DB::transaction(function () use ($supplier, $validated, $items, $total, $request) {
            $invoice = SupplierInvoice::create([
                'supplier_id'        => $supplier->id,
                'invoice_number'     => $validated['invoice_number'] ?? null,
                'invoice_date'       => $validated['invoice_date'],
                'due_date'           => $validated['due_date'] ?? null,
                'total_amount'       => $total,
                'paid_amount'        => '0',
                'balance'            => $total,
                'status'             => 'PENDING',
                'notes'              => $validated['notes'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ($items as $idx => $it) {
                SupplierInvoiceItem::create([
                    'supplier_invoice_id' => $invoice->id,
                    'description'         => $it['description'],
                    'sale_unit'           => $it['sale_unit'],
                    'quantity'            => $it['quantity'],
                    'unit_price'          => $it['unit_price'],
                    'line_total'          => $it['line_total'],
                    'sort_order'          => $idx,
                ]);
            }

            return $invoice;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Factura de proveedor registrada.',
                'invoice' => [
                    'id'             => $invoice->id,
                    'supplier_id'    => $invoice->supplier_id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount'   => (string) $invoice->total_amount,
                    'balance'        => (string) $invoice->balance,
                ],
            ], 201);
        }

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Factura de proveedor registrada.');
    }
}
