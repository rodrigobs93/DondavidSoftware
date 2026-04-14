<?php

namespace App\Http\Controllers;

use App\Models\CustomerPayment;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function payments(Request $request)
    {
        $q              = $request->input('q', '');
        $startDate      = $request->input('start_date', '');
        $endDate        = $request->input('end_date', '');
        $method         = $request->input('method', '');
        $unverifiedOnly = $request->boolean('unverified_only');

        // ── Direct payments (invoice-level or quick-sale, not allocation records) ──
        $paymentQuery = Payment::with([
                'invoice'   => fn($q) => $q->with(['customer' => fn($cq) => $cq->withTrashed()]),
                'quickSale',
            ])
            ->whereNull('customer_payment_id')   // exclude FIFO allocation records
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('invoice_id')
                        ->whereHas('invoice', fn($iq) => $iq->where('voided', false));
                })->orWhereNotNull('quick_sale_id');
            })
            ->where('method', '!=', 'CASH');

        if ($q) {
            $paymentQuery->where(function ($sub) use ($q) {
                $sub->whereHas('invoice', fn($iq) => $iq->where('consecutive', 'ilike', "%{$q}%"))
                    ->orWhereHas('invoice.customer', fn($cq) => $cq
                        ->withTrashed()
                        ->where(fn($q2) => $q2
                            ->where('name', 'ilike', "%{$q}%")
                            ->orWhere('business_name', 'ilike', "%{$q}%")
                        )
                    )
                    ->orWhereHas('quickSale', fn($sq) => $sq->where('receipt_number', 'ilike', "%{$q}%"));
            });
        }
        if ($startDate) $paymentQuery->whereDate('paid_at', '>=', $startDate);
        if ($endDate)   $paymentQuery->whereDate('paid_at', '<=', $endDate);
        if ($method)    $paymentQuery->where('method', $method);
        if ($unverifiedOnly) $paymentQuery->where('verified', false);

        $toPaymentRow = fn(Payment $p) => [
            '_type'          => 'payment',
            'id'             => $p->id,
            'invoice_id'     => $p->invoice_id,
            'quick_sale_id'  => $p->quick_sale_id,
            'consecutive'    => $p->invoice->consecutive ?? $p->quickSale->receipt_number ?? '—',
            'customer_name'  => $p->invoice->customer->name ?? 'Venta rápida',
            'business_name'  => $p->invoice->customer->business_name ?? null,
            'method'         => $p->method,
            'method_label'   => $p->method_label,
            'amount'         => (string) $p->amount,
            'paid_at'        => $p->paid_at->setTimezone('America/Bogota')->format('d/m/Y H:i'),
            'verified'       => $p->verified,
            'verified_at'    => $p->verified_at?->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ];

        // ── Consolidated customer payments (single platform transaction per record) ──
        $cpQuery = CustomerPayment::with(['customer' => fn($q) => $q->withTrashed()])
            ->where('method', '!=', 'CASH');

        if ($q) {
            $cpQuery->whereHas('customer', fn($cq) => $cq
                ->withTrashed()
                ->where(fn($q2) => $q2
                    ->where('name', 'ilike', "%{$q}%")
                    ->orWhere('business_name', 'ilike', "%{$q}%")
                )
            );
        }
        if ($startDate)      $cpQuery->whereDate('paid_at', '>=', $startDate);
        if ($endDate)        $cpQuery->whereDate('paid_at', '<=', $endDate);
        if ($method)         $cpQuery->where('method', $method);
        if ($unverifiedOnly) $cpQuery->where('verified', false);

        $toCpRow = fn(CustomerPayment $cp) => [
            '_type'          => 'customer_payment',
            'id'             => $cp->id,
            'invoice_id'     => null,
            'quick_sale_id'  => null,
            'consecutive'    => 'COBRO',   // label for verification list
            'customer_name'  => $cp->customer?->name ?? '—',
            'business_name'  => $cp->customer?->business_name ?? null,
            'method'         => $cp->method,
            'method_label'   => $cp->method_label,
            'amount'         => (string) $cp->amount,
            'paid_at'        => $cp->paid_at->setTimezone('America/Bogota')->format('d/m/Y H:i'),
            'verified'       => $cp->verified,
            'verified_at'    => $cp->verified_at?->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ];

        if ($request->wantsJson()) {
            $payments   = $paymentQuery->orderByRaw('verified ASC, paid_at DESC')->get()->map($toPaymentRow);
            $cpRows     = $cpQuery->orderByRaw('verified ASC, paid_at DESC')->get()->map($toCpRow);

            // Merge and re-sort: unverified first, then by paid_at DESC
            $merged = $payments->concat($cpRows)->sortBy([
                ['verified', 'asc'],
                ['paid_at',  'desc'],
            ])->values();

            return response()->json($merged);
        }

        $payments    = $paymentQuery->orderByRaw('verified ASC, paid_at DESC')->paginate(50)->withQueryString();
        $cpRows      = $cpQuery->orderByRaw('verified ASC, paid_at DESC')->get()->map($toCpRow);
        $initialData = $payments->map($toPaymentRow)->concat($cpRows)->sortBy([
            ['verified', 'asc'],
            ['paid_at',  'desc'],
        ])->values();

        return view('reports.payments', compact(
            'payments', 'initialData', 'q', 'startDate', 'endDate', 'method', 'unverifiedOnly'
        ));
    }

    /** Verify a direct Payment record. */
    public function verifyPayment(Payment $payment)
    {
        $payment->update([
            'verified'            => true,
            'verified_at'         => now(),
            'verified_by_user_id' => auth()->id(),
        ]);

        return response()->json([
            'ok'          => true,
            'verified_at' => $payment->verified_at->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ]);
    }

    /** Verify a consolidated CustomerPayment record. */
    public function verifyCustomerPayment(CustomerPayment $customerPayment)
    {
        $customerPayment->update([
            'verified'            => true,
            'verified_at'         => now(),
            'verified_by_user_id' => auth()->id(),
        ]);

        return response()->json([
            'ok'          => true,
            'verified_at' => $customerPayment->verified_at->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ]);
    }

    /** Bulk verify — supports both Payment and CustomerPayment IDs. */
    public function verifyBulk(Request $request)
    {
        $data = $request->validate([
            'ids'    => ['nullable', 'array'],
            'ids.*'  => ['integer'],
            'cp_ids' => ['nullable', 'array'],
            'cp_ids.*' => ['integer'],
        ]);

        $ids   = $data['ids']    ?? [];
        $cpIds = $data['cp_ids'] ?? [];

        if (empty($ids) && empty($cpIds)) {
            return response()->json(['error' => 'No IDs provided.'], 422);
        }

        $now = now();

        if (!empty($ids)) {
            Payment::whereIn('id', $ids)->where('verified', false)->update([
                'verified'            => true,
                'verified_at'         => $now,
                'verified_by_user_id' => auth()->id(),
                'updated_at'          => $now,
            ]);
        }
        if (!empty($cpIds)) {
            CustomerPayment::whereIn('id', $cpIds)->where('verified', false)->update([
                'verified'            => true,
                'verified_at'         => $now,
                'verified_by_user_id' => auth()->id(),
                'updated_at'          => $now,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
