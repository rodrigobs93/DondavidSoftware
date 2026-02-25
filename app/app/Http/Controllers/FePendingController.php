<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

class FePendingController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('customer')
            ->where('fe_status', 'PENDING')
            ->where('voided', false)
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return view('fe-pending.index', compact('invoices'));
    }
}
