<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\QuickSale;
use App\Models\Setting;
use App\Models\User;
use App\Services\EscPosTicketRenderer;
use App\Services\ThermalPrinterService;
use Illuminate\Support\Facades\DB;

class QuickSaleService
{
    public function createQuickSale(array $data, User $createdBy): QuickSale
    {
        return DB::transaction(function () use ($data, $createdBy) {
            // 1. Atomic receipt number via PostgreSQL SEQUENCE
            $row = DB::selectOne("SELECT nextval('receipt_consecutive_seq') AS val");
            $int = (int) $row->val;
            $receiptNumber = 'R-' . str_pad($int, 6, '0', STR_PAD_LEFT);

            // 2. Precision arithmetic + Colombia cash rounding
            $total    = $this->roundUp50(bcadd('0', (string) $data['total_amount'], 2));
            $method   = $data['payment_method'];

            $cashReceived = null;
            $changeAmount = null;
            if ($method === 'CASH') {
                $cashReceived = bcadd('0', (string) ($data['cash_received'] ?? $total), 2);
                $diff         = bcsub($cashReceived, $total, 2);
                $changeAmount = bccomp($diff, '0', 2) >= 0 ? $diff : '0.00';
            }

            // 3. Insert quick_sale record
            $qs = QuickSale::create([
                'receipt_number'      => $receiptNumber,
                'receipt_int'         => $int,
                'sale_date'           => $data['sale_date'] ?? now()->setTimezone('America/Bogota')->toDateString(),
                'total_amount'        => $total,
                'payment_method'      => $method,
                'cash_received'       => $cashReceived,
                'change_amount'       => $changeAmount,
                'notes'               => $data['notes'] ?? null,
                'created_by_user_id'  => $createdBy->id,
                'submission_key'      => $data['submission_key'] ?? null,
            ]);

            // 4. Insert payment (verified = false by default for non-cash)
            Payment::create([
                'quick_sale_id'          => $qs->id,
                'method'                 => $method,
                'amount'                 => $total,
                'paid_at'                => now(),
                'registered_by_user_id'  => $createdBy->id,
            ]);

            return $qs;
        });
    }

    private function roundUp50(string $amount): string
    {
        $n   = (int) round((float) $amount);
        $mod = $n % 50;
        if ($mod === 0) return number_format($n, 2, '.', '');
        return number_format($n + 50 - $mod, 2, '.', '');
    }

    public function createPrintJobForQuickSale(QuickSale $qs): PrintJob
    {
        $shop = Setting::shopInfo();

        $payload = [
            'type'    => 'quick_sale',
            'shop'    => $shop,
            'receipt' => [
                'number'        => $qs->receipt_number,
                'date'          => $qs->sale_date->format('d/m/Y'),
                'time'          => now()->setTimezone('America/Bogota')->format('H:i'),
                'total'         => (string) $qs->total_amount,
                'method'        => $qs->payment_method,
                'method_label'  => Payment::$methods[$qs->payment_method] ?? $qs->payment_method,
                'cash_received' => (string) ($qs->cash_received ?? $qs->total_amount),
                'change_amount' => (string) ($qs->change_amount ?? '0.00'),
            ],
        ];

        $job = PrintJob::create([
            'quick_sale_id' => $qs->id,
            'status'        => 'PRINTING',
            'payload'       => $payload,
            'attempts'      => 1,
            'queued_at'     => now(),
        ]);

        // Print synchronously — no worker daemon needed
        try {
            $bytes = (new EscPosTicketRenderer())->renderQuickSale($payload);
            (new ThermalPrinterService())->send($bytes);
            $job->update(['status' => 'PRINTED', 'printed_at' => now()]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'FAILED', 'error_message' => $e->getMessage()]);
        }

        return $job;
    }
}
