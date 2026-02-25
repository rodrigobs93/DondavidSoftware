<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PrintJob;
use App\Models\Setting;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->setTimezone('America/Bogota')->toDateString();

        $todayStats = Invoice::where('invoice_date', $today)
            ->where('voided', false)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total_sum')
            ->first();

        $carteraCount   = Invoice::where('balance', '>', 0)->where('voided', false)->count();
        $fePendingCount = Invoice::where('fe_status', 'PENDING')->where('voided', false)->count();

        $printErrors = PrintJob::where('status', 'FAILED')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $lanIp = Setting::get('lan_ip', config('app.lan_ip', '192.168.1.100'));

        return view('dashboard', compact(
            'todayStats', 'carteraCount', 'fePendingCount', 'printErrors', 'lanIp'
        ));
    }
}
