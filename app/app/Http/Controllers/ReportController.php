<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function payments(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->setTimezone('America/Bogota')->toDateString());
        $dateTo   = $request->input('date_to', $dateFrom);

        $fromUTC = Carbon::parse($dateFrom, 'America/Bogota')->startOfDay()->utc();
        $toUTC   = Carbon::parse($dateTo, 'America/Bogota')->endOfDay()->utc();

        $byMethod = Payment::whereBetween('paid_at', [$fromUTC, $toUTC])
            ->whereHas('invoice', fn($q) => $q->where('voided', false))
            ->select('method', DB::raw('SUM(amount) as total, COUNT(*) as count'))
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $totalSales = Invoice::whereDate('invoice_date', '>=', $dateFrom)
            ->whereDate('invoice_date', '<=', $dateTo)
            ->where('voided', false)
            ->sum('total');

        $totalBalance = Invoice::where('balance', '>', 0)->where('voided', false)->sum('balance');

        return view('reports.payments', compact(
            'byMethod', 'totalSales', 'totalBalance', 'dateFrom', 'dateTo'
        ));
    }
}
