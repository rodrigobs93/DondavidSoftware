<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\QuickSale;
use App\Services\QuickSaleService;
use Illuminate\Http\Request;

class QuickSaleController extends Controller
{
    public function __construct(private QuickSaleService $quickSaleService) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'total_amount'   => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:CASH,CARD,NEQUI,DAVIPLATA,BREB'],
            'cash_received'  => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:255'],
            'submission_key' => ['nullable', 'string', 'max:64'],
        ]);

        // Idempotency: same key → return existing quick sale
        if (!empty($validated['submission_key'])) {
            $existing = QuickSale::where('submission_key', $validated['submission_key'])->first();
            if ($existing) {
                return response()->json($this->toJson($existing));
            }
        }

        // CASH: if cash_received is provided, it must cover the total.
        // If omitted, the sale is finalized without tracking cash/change.
        if ($validated['payment_method'] === 'CASH'
            && isset($validated['cash_received'])
            && (float) $validated['cash_received'] < (float) $validated['total_amount']) {
            return response()->json([
                'errors' => ['cash_received' => 'El efectivo recibido no puede ser menor al total.'],
            ], 422);
        }

        $qs = $this->quickSaleService->createQuickSale($validated, auth()->user());

        return response()->json($this->toJson($qs), 201);
    }

    public function show(QuickSale $quickSale)
    {
        $quickSale->load(['createdBy', 'printJobs' => fn($q) => $q->latest()->limit(3)]);
        return view('quick-sales.show', compact('quickSale'));
    }

    public function print(QuickSale $quickSale)
    {
        $job = $this->quickSaleService->createPrintJobForQuickSale($quickSale);
        if ($job->status === 'FAILED') {
            return response()->json(['ok' => false, 'error' => $job->error_message], 500);
        }
        return response()->json(['ok' => true]);
    }

    private function toJson(QuickSale $qs): array
    {
        return [
            'id'             => $qs->id,
            'receipt_number' => $qs->receipt_number,
            'total'          => (string) $qs->total_amount,
            'method'         => $qs->payment_method,
            'method_label'   => Payment::$methods[$qs->payment_method] ?? $qs->payment_method,
            'cash_received'  => $qs->cash_received !== null ? (string) $qs->cash_received : null,
            'change'         => $qs->change_amount !== null ? (string) $qs->change_amount : null,
            'is_cash'        => $qs->payment_method === 'CASH',
        ];
    }
}
