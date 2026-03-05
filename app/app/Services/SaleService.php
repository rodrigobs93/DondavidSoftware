<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function createSale(array $data, User $createdBy): Invoice
    {
        return DB::transaction(function () use ($data, $createdBy) {
            // 1. Generate consecutive number atomically via PostgreSQL SEQUENCE
            $row = DB::selectOne("SELECT nextval('invoice_consecutive_seq') AS val");
            $consecutiveInt = (int) $row->val;
            $consecutive = str_pad($consecutiveInt, 7, '0', STR_PAD_LEFT);

            // 2. Compute totals with bcmath (no floating-point)
            $subtotal = '0';
            foreach ($data['items'] as &$item) {
                $lineTotal = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                $item['line_total'] = $lineTotal;
                $subtotal = bcadd($subtotal, $lineTotal, 2);
            }
            unset($item);

            $deliveryFee = bcadd('0', (string) ($data['delivery_fee'] ?? '0'), 2);
            $total = bcadd($subtotal, $deliveryFee, 2);

            $paidAmount = '0';
            foreach ($data['payments'] as $payment) {
                $paidAmount = bcadd($paidAmount, (string) $payment['amount'], 2);
            }
            $balance = bcsub($total, $paidAmount, 2);

            $status = match (true) {
                bccomp($balance, '0', 2) === 0                => 'PAID',
                bccomp($paidAmount, '0', 2) === 0             => 'PENDING',
                default                                        => 'PARTIAL',
            };

            $requiresFe = !empty($data['requires_fe']);
            $feStatus = $requiresFe ? 'PENDING' : 'NONE';

            // 3. Insert invoice
            $invoice = Invoice::create([
                'consecutive'        => $consecutive,
                'consecutive_int'    => $consecutiveInt,
                'customer_id'        => $data['customer_id'],
                'created_by_user_id' => $createdBy->id,
                'invoice_date'       => $data['invoice_date'] ?? now()->setTimezone('America/Bogota')->toDateString(),
                'subtotal'           => $subtotal,
                'delivery_fee'       => $deliveryFee,
                'total'              => $total,
                'paid_amount'        => $paidAmount,
                'balance'            => $balance,
                'status'             => $status,
                'requires_fe'        => $requiresFe,
                'fe_status'          => $feStatus,
                'notes'              => $data['notes'] ?? null,
                'submission_key'     => $data['submission_key'] ?? null,
            ]);

            // 4. Insert items (snapshots — immutable)
            foreach ($data['items'] as $idx => $item) {
                InvoiceItem::create([
                    'invoice_id'            => $invoice->id,
                    'product_id'            => $item['product_id'] ?? null,
                    'product_name_snapshot' => $item['product_name'],
                    'sale_unit_snapshot'    => $item['sale_unit'],
                    'quantity'              => $item['quantity'],
                    'unit_price'            => $item['unit_price'],
                    'line_total'            => $item['line_total'],
                    'sort_order'            => $idx,
                ]);
            }

            // 5. Insert payments
            foreach ($data['payments'] as $payment) {
                Payment::create([
                    'invoice_id'             => $invoice->id,
                    'method'                 => $payment['method'],
                    'amount'                 => $payment['amount'],
                    'paid_at'                => now(),
                    'registered_by_user_id'  => $createdBy->id,
                ]);
            }

            // 6. Queue print job
            $this->createPrintJob($invoice->load(['customer', 'items', 'payments']));

            return $invoice;
        });
    }

    public function addPayment(Invoice $invoice, array $data, User $registeredBy): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $registeredBy) {
            $amount = bcadd('0', (string) $data['amount'], 2);

            if (bccomp($amount, (string) $invoice->balance, 2) > 0) {
                throw new \InvalidArgumentException('El abono no puede superar el saldo pendiente.');
            }

            $payment = Payment::create([
                'invoice_id'             => $invoice->id,
                'method'                 => $data['method'],
                'amount'                 => $amount,
                'paid_at'                => now(),
                'notes'                  => $data['notes'] ?? null,
                'registered_by_user_id'  => $registeredBy->id,
            ]);

            $newPaidAmount = bcadd((string) $invoice->paid_amount, $amount, 2);
            $newBalance    = bcsub((string) $invoice->total, $newPaidAmount, 2);
            $newStatus     = bccomp($newBalance, '0', 2) === 0 ? 'PAID' : 'PARTIAL';

            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'balance'     => $newBalance,
                'status'      => $newStatus,
            ]);

            return $payment;
        });
    }

    public function markFeIssued(Invoice $invoice, string $feReference, User $issuedBy): void
    {
        DB::transaction(function () use ($invoice, $feReference, $issuedBy) {
            $invoice->update([
                'fe_status'            => 'ISSUED',
                'fe_reference'         => $feReference,
                'fe_issued_at'         => now(),
                'fe_issued_by_user_id' => $issuedBy->id,
            ]);

            $this->createPrintJob($invoice->fresh()->load(['customer', 'items', 'payments']));
        });
    }

    public function createPrintJob(Invoice $invoice): PrintJob
    {
        if (!$invoice->relationLoaded('customer')) {
            $invoice->load(['customer', 'items', 'payments']);
        }

        $shop = Setting::shopInfo();

        $payload = [
            'shop'     => $shop,
            'invoice'  => [
                'id'           => $invoice->id,
                'consecutive'  => $invoice->consecutive,
                'date'         => $invoice->invoice_date->format('d/m/Y'),
                'time'         => now()->setTimezone('America/Bogota')->format('H:i'),
                'subtotal'     => (string) $invoice->subtotal,
                'delivery_fee' => (string) $invoice->delivery_fee,
                'total'        => (string) $invoice->total,
                'paid_amount'  => (string) $invoice->paid_amount,
                'balance'      => (string) $invoice->balance,
                'requires_fe'  => $invoice->requires_fe,
                'fe_status'    => $invoice->fe_status,
                'fe_reference' => $invoice->fe_reference,
                'fe_label'     => $invoice->fe_label,
            ],
            'customer' => [
                'name'          => $invoice->customer->name,
                'business_name' => $invoice->customer->business_name,
                'is_generic'    => $invoice->customer->is_generic,
                'doc_type'      => $invoice->customer->doc_type,
                'doc_number'    => $invoice->customer->doc_number,
                'doc_label'     => $invoice->customer->doc_label,
            ],
            'items'    => $invoice->items->map(fn($item) => [
                'product_name_snapshot' => $item->product_name_snapshot,
                'sale_unit_snapshot'    => $item->sale_unit_snapshot,
                'quantity'              => (string) $item->quantity,
                'unit_price'            => (string) $item->unit_price,
                'line_total'            => (string) $item->line_total,
                'formatted_quantity'    => $item->formatted_quantity,
            ])->toArray(),
            'payments' => $invoice->payments->map(fn($p) => [
                'method'       => $p->method,
                'method_label' => $p->method_label,
                'amount'       => (string) $p->amount,
            ])->toArray(),
        ];

        return PrintJob::create([
            'invoice_id' => $invoice->id,
            'status'     => 'QUEUED',
            'payload'    => $payload,
            'queued_at'  => now(),
        ]);
    }
}
