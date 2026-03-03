<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function payments(Request $request)
    {
        $q              = $request->input('q', '');
        $startDate      = $request->input('start_date', now()->setTimezone('America/Bogota')->toDateString());
        $endDate        = $request->input('end_date', $startDate);
        $method         = $request->input('method', '');
        $unverifiedOnly = $request->boolean('unverified_only');

        $query = Payment::with([
                'invoice' => fn($q) => $q->with([
                    'customer' => fn($cq) => $cq->withTrashed(),
                ]),
            ])
            ->whereHas('invoice', fn($q) => $q->where('voided', false))
            ->where('method', '!=', 'CASH')
            ->orderByRaw('verified ASC, paid_at DESC');

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->whereHas('invoice', fn($iq) => $iq->where('consecutive', 'ilike', "%{$q}%"))
                    ->orWhereHas('invoice.customer', fn($cq) => $cq
                        ->withTrashed()
                        ->where(fn($q2) => $q2
                            ->where('name', 'ilike', "%{$q}%")
                            ->orWhere('business_name', 'ilike', "%{$q}%")
                        )
                    );
            });
        }

        if ($startDate) {
            $query->whereDate('paid_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('paid_at', '<=', $endDate);
        }
        if ($method) {
            $query->where('method', $method);
        }
        if ($unverifiedOnly) {
            $query->where('verified', false);
        }

        $toRow = fn(Payment $p) => [
            'id'            => $p->id,
            'invoice_id'    => $p->invoice_id,
            'consecutive'   => $p->invoice->consecutive ?? '—',
            'customer_name' => $p->invoice->customer->name ?? '—',
            'business_name' => $p->invoice->customer->business_name ?? null,
            'method'        => $p->method,
            'method_label'  => $p->method_label,
            'amount'        => (string) $p->amount,
            'paid_at'       => $p->paid_at->setTimezone('America/Bogota')->format('d/m/Y H:i'),
            'verified'      => $p->verified,
            'verified_at'   => $p->verified_at?->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ];

        if ($request->wantsJson()) {
            return response()->json($query->get()->map($toRow)->values());
        }

        $payments    = $query->paginate(50)->withQueryString();
        $initialData = $payments->map($toRow);

        return view('reports.payments', compact(
            'payments', 'initialData', 'q', 'startDate', 'endDate', 'method', 'unverifiedOnly'
        ));
    }

    public function verifyPayment(Payment $payment)
    {
        $payment->update([
            'verified'              => true,
            'verified_at'           => now(),
            'verified_by_user_id'   => auth()->id(),
        ]);

        return response()->json([
            'ok'          => true,
            'verified_at' => $payment->verified_at->setTimezone('America/Bogota')->format('d/m/Y H:i'),
        ]);
    }

    public function verifyBulk(Request $request)
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $count = Payment::whereIn('id', $ids)
            ->where('verified', false)
            ->update([
                'verified'              => true,
                'verified_at'           => now(),
                'verified_by_user_id'   => auth()->id(),
                'updated_at'            => now(),
            ]);

        return response()->json(['ok' => true, 'count' => $count]);
    }
}
