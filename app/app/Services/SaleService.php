<?php

namespace App\Services;

use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\EscPosTicketRenderer;
use App\Services\ThermalPrinterService;
use Illuminate\Support\Facades\DB;

class SaleService
{
    // Constructor DI (same pattern as CarteraController/CotizacionController)
    // so the printer can be mocked in tests. SaleService is only ever resolved
    // through the container — never `new`ed — so every call site keeps working.
    public function __construct(
        private EscPosTicketRenderer $renderer,
        private ThermalPrinterService $printer,
    ) {}

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
            $requiresFe = !empty($data['requires_fe']);
            $feStatus = $requiresFe ? 'PENDING' : 'NONE';

            // FE invoices keep cent-exact totals (legal requirement); cash
            // tickets round up to the nearest 50 COP for change convenience.
            $rawTotal = bcadd($subtotal, $deliveryFee, 2);
            $total    = $requiresFe ? $rawTotal : $this->roundUp50($rawTotal);

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

    /**
     * Correct an existing invoice in place (admin-only feature).
     *
     * Recomputes every money field with the same bcmath/rounding rules as
     * createSale, replaces the item snapshots, and reconciles the stored
     * paid_amount/balance/status. Payment rows are never touched: if the new
     * total drops below what was already paid, the excess is refunded to the
     * customer's credit_balance and recorded as an immutable REFUND_FROM_EDIT
     * ledger entry (mirror of CarteraController::applyCredit, which raises
     * paid_amount without a Payment row). Runs inside a transaction with the
     * invoice + customer row-locked so concurrent edits/payments cannot leave
     * the invoice inconsistent. Never reassigns the consecutive and never
     * re-prints — the existing "Reimprimir" button covers a corrected ticket.
     */
    public function updateSale(Invoice $invoice, array $data, User $editedBy): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $editedBy) {
            // Re-fetch with row locks to serialize concurrent edits / abonos.
            $inv  = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $cust = Customer::lockForUpdate()->findOrFail($data['customer_id'] ?? $inv->customer_id);

            // 1. Recompute totals with bcmath (same as createSale).
            $subtotal = '0';
            foreach ($data['items'] as &$item) {
                $lineTotal = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                $item['line_total'] = $lineTotal;
                $subtotal = bcadd($subtotal, $lineTotal, 2);
            }
            unset($item);

            $deliveryFee = bcadd('0', (string) ($data['delivery_fee'] ?? '0'), 2);
            // FE flag is not toggled during edit; keep the invoice's own rule so
            // FE invoices stay cent-exact and cash tickets keep the 50-COP round-up.
            $requiresFe = (bool) $inv->requires_fe;
            $rawTotal   = bcadd($subtotal, $deliveryFee, 2);
            $total      = $requiresFe ? $rawTotal : $this->roundUp50($rawTotal);

            // 2. Reconcile against what was already paid/allocated (payments untouched).
            $paidAmount = (string) $inv->paid_amount;
            $newBalance = bcsub($total, $paidAmount, 2);

            if (bccomp($newBalance, '0', 2) < 0) {
                // New total is below what was paid → refund the excess to credit.
                $excess = bcsub($paidAmount, $total, 2);

                $cust->update([
                    'credit_balance' => bcadd((string) $cust->credit_balance, $excess, 2),
                ]);

                CreditMovement::create([
                    'customer_id'        => $cust->id,
                    'invoice_id'         => $inv->id,
                    'amount'             => $excess,
                    'type'               => 'REFUND_FROM_EDIT',
                    'created_by_user_id' => $editedBy->id,
                    'notes'              => 'Excedente por edición de la factura #' . $inv->consecutive,
                ]);

                $paidAmount = $total;
                $newBalance = '0.00';
                $status     = 'PAID';
            } else {
                $status = match (true) {
                    bccomp($newBalance, '0', 2) === 0 => 'PAID',
                    bccomp($paidAmount, '0', 2) === 0 => 'PENDING',
                    default                           => 'PARTIAL',
                };
            }

            // 3. Update the invoice header.
            $inv->update([
                'customer_id'  => $cust->id,
                'invoice_date' => $data['invoice_date'] ?? $inv->invoice_date,
                'subtotal'     => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total'        => $total,
                'paid_amount'  => $paidAmount,
                'balance'      => $newBalance,
                'status'       => $status,
                'notes'        => $data['notes'] ?? null,
            ]);

            // 4. Replace the item snapshots (write-once rows: delete + reinsert).
            $inv->items()->delete();
            foreach (array_values($data['items']) as $idx => $item) {
                InvoiceItem::create([
                    'invoice_id'            => $inv->id,
                    'product_id'            => $item['product_id'] ?? null,
                    'product_name_snapshot' => $item['product_name'],
                    'sale_unit_snapshot'    => $item['sale_unit'],
                    'quantity'              => $item['quantity'],
                    'unit_price'            => $item['unit_price'],
                    'line_total'            => $item['line_total'],
                    'sort_order'            => $idx,
                ]);
            }

            return $inv->refresh();
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
        $invoice->update([
            'fe_status'            => 'ISSUED',
            'fe_reference'         => $feReference,
            'fe_issued_at'         => now(),
            'fe_issued_by_user_id' => $issuedBy->id,
        ]);
    }

    private function roundUp50(string $amount): string
    {
        $n   = (int) round((float) $amount);
        $mod = $n % 50;
        if ($mod === 0) return number_format($n, 2, '.', '');
        return number_format($n + 50 - $mod, 2, '.', '');
    }

    public function buildInvoicePayload(Invoice $invoice): array
    {
        if (!$invoice->relationLoaded('customer')) {
            $invoice->load(['customer', 'items', 'payments']);
        }

        return [
            'shop'     => Setting::shopInfo(),
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
    }

    public function createPrintJob(Invoice $invoice): PrintJob
    {
        $payload = $this->buildInvoicePayload($invoice);

        $job = PrintJob::create([
            'invoice_id' => $invoice->id,
            'status'     => 'PRINTING',
            'payload'    => $payload,
            'attempts'   => 1,
            'queued_at'  => now(),
        ]);

        // Print synchronously — no worker daemon needed
        try {
            $bytes = $this->renderer->render($payload);
            $this->printer->send($bytes);
            $job->update(['status' => 'PRINTED', 'printed_at' => now()]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'FAILED', 'error_message' => $e->getMessage()]);
        }

        return $job;
    }
}
