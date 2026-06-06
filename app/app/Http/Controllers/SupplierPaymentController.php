<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\SupplierPaymentService;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    public function __construct(
        private SupplierPaymentService $service,
    ) {}

    /** Supplier-level (unlinked) payment — auto-allocated FIFO. */
    public function storeConsolidated(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'method'         => ['required', 'in:CASH,NEQUI,DAVIPLATA,DAVIVIENDA,OTHER'],
            'amount'         => ['required', 'integer', 'min:1'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'submission_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $this->service->applyConsolidatedPayment(
                $supplier,
                (string) $validated['amount'],
                $validated['method'],
                $validated['notes'] ?? null,
                $request->user(),
                $validated['submission_key'] ?? null,
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
            }
            return back()->withErrors(['payment' => 'Error: ' . $e->getMessage()]);
        }

        $msg = 'Pago registrado.';
        if (bccomp($result['credit_added'], '0', 2) > 0) {
            $msg .= ' Quedó saldo a favor de ' . format_cop($result['credit_added']) . '.';
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $msg], 201);
        }

        return redirect()->route('suppliers.show', $supplier)->with('success', $msg);
    }

    /** Invoice-linked payment. */
    public function storeInvoicePayment(Request $request, SupplierInvoice $supplierInvoice)
    {
        $validated = $request->validate([
            'method'         => ['required', 'in:CASH,NEQUI,DAVIPLATA,DAVIVIENDA,OTHER'],
            'amount'         => ['required', 'integer', 'min:1'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'submission_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->service->applyInvoicePayment(
                $supplierInvoice,
                (string) $validated['amount'],
                $validated['method'],
                $validated['notes'] ?? null,
                $request->user(),
                $validated['submission_key'] ?? null,
            );
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Abono registrado.'], 201);
        }

        return redirect()->route('suppliers.show', $supplierInvoice->supplier_id)
            ->with('success', 'Abono registrado.');
    }
}
