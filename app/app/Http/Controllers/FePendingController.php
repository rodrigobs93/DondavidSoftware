<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class FePendingController extends Controller
{
    public function index(Request $request)
    {
        $q         = $request->input('q', '');
        $feStatus  = $request->input('fe_status', '');
        $startDate = $request->input('start_date', '');
        $endDate   = $request->input('end_date', '');

        // Base: all invoices that require electronic invoice (PENDING or ISSUED)
        $query = Invoice::with('customer')
            ->where('requires_fe', true)
            ->where('voided', false)
            ->orderBy('invoice_date', 'asc');

        $query->applyFilters($q, $startDate, $endDate);

        if ($feStatus) {
            $query->where('fe_status', $feStatus);
        }

        $toRow = fn($inv) => [
            'id'            => $inv->id,
            'consecutive'   => $inv->consecutive,
            'invoice_date'  => $inv->invoice_date->format('d/m/Y'),
            'customer_name' => $inv->customer?->name ?? '—',
            'customer_doc'  => $inv->customer?->doc_label ?? '—',
            'total'         => (string) $inv->total,
            'fe_status'     => $inv->fe_status,
            'fe_reference'  => $inv->fe_reference ?? '',
        ];

        if ($request->wantsJson()) {
            return response()->json($query->get()->map($toRow));
        }

        $invoices    = $query->paginate(20)->withQueryString();
        $initialData = $invoices->map($toRow);

        return view('fe-pending.index', compact(
            'invoices', 'initialData', 'q', 'feStatus', 'startDate', 'endDate'
        ));
    }
}
