<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    /**
     * Apply a supplier-level (unlinked) payment across the supplier's outstanding
     * invoices using FIFO — oldest pending invoice first.
     *
     * The original payment stays a single supplier_payments record; the distribution
     * is recorded in supplier_payment_allocations for audit. Any amount exceeding the
     * total outstanding debt is added to the supplier's credit_balance (saldo a favor).
     *
     * @return array{payment: SupplierPayment, allocated: string, credit_added: string, invoices_fully_paid: int}
     */
    public function applyConsolidatedPayment(
        Supplier $supplier,
        string   $amount,
        string   $method,
        ?string  $notes,
        User     $user,
        ?string  $submissionKey = null
    ): array {
        // Double-submit guard: if this submission was already processed, return it.
        if ($submissionKey) {
            $existing = SupplierPayment::where('submission_key', $submissionKey)->first();
            if ($existing) {
                return [
                    'payment'             => $existing,
                    'allocated'           => (string) $existing->allocations()->sum('allocated_amount'),
                    'credit_added'        => '0.00',
                    'invoices_fully_paid' => 0,
                ];
            }
        }

        return DB::transaction(function () use ($supplier, $amount, $method, $notes, $user, $submissionKey) {

            // Lock pending invoices (FIFO order) to prevent races with concurrent requests.
            $invoices = $supplier->pendingInvoices()
                                 ->lockForUpdate()
                                 ->get();

            $payment = SupplierPayment::create([
                'supplier_id'           => $supplier->id,
                'supplier_invoice_id'   => null,
                'amount'                => $amount,
                'method'                => $method,
                'paid_at'               => now(),
                'notes'                 => $notes,
                'registered_by_user_id' => $user->id,
                'submission_key'        => $submissionKey,
            ]);

            $remaining         = $amount;
            $allocated         = '0.00';
            $invoicesFullyPaid = 0;

            foreach ($invoices as $invoice) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }

                $apply = $this->bcmin($remaining, (string) $invoice->balance);

                SupplierPaymentAllocation::create([
                    'supplier_payment_id' => $payment->id,
                    'supplier_invoice_id' => $invoice->id,
                    'allocated_amount'    => $apply,
                ]);

                $newPaidAmount = bcadd((string) $invoice->paid_amount, $apply, 2);
                $newBalance    = bcsub((string) $invoice->total_amount, $newPaidAmount, 2);
                $newStatus     = bccomp($newBalance, '0', 2) === 0 ? 'PAID' : 'PARTIAL';

                $invoice->paid_amount = $newPaidAmount;
                $invoice->balance     = $newBalance;
                $invoice->status      = $newStatus;
                $invoice->save();

                if ($newStatus === 'PAID') {
                    $invoicesFullyPaid++;
                }

                $allocated = bcadd($allocated, $apply, 2);
                $remaining = bcsub($remaining, $apply, 2);
            }

            // Any remainder becomes supplier credit (saldo a favor).
            $creditAdded = '0.00';
            if (bccomp($remaining, '0', 2) > 0) {
                $creditAdded = $remaining;
                $supplier->credit_balance = bcadd((string) $supplier->credit_balance, $remaining, 2);
                $supplier->save();
            }

            return [
                'payment'             => $payment,
                'allocated'           => $allocated,
                'credit_added'        => $creditAdded,
                'invoices_fully_paid' => $invoicesFullyPaid,
            ];
        });
    }

    /**
     * Apply a payment directly to a single supplier invoice (invoice-linked mode).
     * Records one allocation row so the audit trail is uniform with FIFO payments.
     */
    public function applyInvoicePayment(
        SupplierInvoice $invoice,
        string   $amount,
        string   $method,
        ?string  $notes,
        User     $user,
        ?string  $submissionKey = null
    ): SupplierPayment {
        if ($submissionKey) {
            $existing = SupplierPayment::where('submission_key', $submissionKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $notes, $user, $submissionKey) {
            $inv = SupplierInvoice::lockForUpdate()->findOrFail($invoice->id);

            if (bccomp((string) $inv->balance, '0', 2) <= 0) {
                throw new \InvalidArgumentException('La factura ya está pagada.');
            }
            if (bccomp($amount, (string) $inv->balance, 2) > 0) {
                throw new \InvalidArgumentException('El abono no puede superar el saldo pendiente.');
            }

            $payment = SupplierPayment::create([
                'supplier_id'           => $inv->supplier_id,
                'supplier_invoice_id'   => $inv->id,
                'amount'                => $amount,
                'method'                => $method,
                'paid_at'               => now(),
                'notes'                 => $notes,
                'registered_by_user_id' => $user->id,
                'submission_key'        => $submissionKey,
            ]);

            SupplierPaymentAllocation::create([
                'supplier_payment_id' => $payment->id,
                'supplier_invoice_id' => $inv->id,
                'allocated_amount'    => $amount,
            ]);

            $newPaidAmount = bcadd((string) $inv->paid_amount, $amount, 2);
            $newBalance    = bcsub((string) $inv->total_amount, $newPaidAmount, 2);
            $newStatus     = bccomp($newBalance, '0', 2) === 0 ? 'PAID' : 'PARTIAL';

            $inv->update([
                'paid_amount' => $newPaidAmount,
                'balance'     => $newBalance,
                'status'      => $newStatus,
            ]);

            return $payment;
        });
    }

    private function bcmin(string $a, string $b): string
    {
        return bccomp($a, $b, 2) <= 0 ? $a : $b;
    }
}
