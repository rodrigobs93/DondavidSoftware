<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    /**
     * Apply a consolidated payment to a customer's outstanding invoices (FIFO).
     *
     * Distributes the payment across the oldest pending invoices first.
     * Any amount exceeding the total outstanding balance is added to the
     * customer's credit_balance (saldo a favor).
     *
     * @return array{allocated: string, credit_added: string, invoices_fully_paid: int}
     */
    public function applyConsolidatedPayment(
        Customer $customer,
        string   $amount,
        string   $method,
        ?string  $notes,
        User     $user
    ): array {
        return DB::transaction(function () use ($customer, $amount, $method, $notes, $user) {

            // Lock pending invoices for this customer (FIFO order) to prevent
            // race conditions with concurrent requests.
            $invoices = $customer->pendingInvoices()
                                 ->lockForUpdate()
                                 ->get();

            // Create the parent CustomerPayment record.
            $customerPayment = CustomerPayment::create([
                'customer_id'           => $customer->id,
                'amount'                => $amount,
                'method'                => $method,
                'paid_at'               => now(),
                'notes'                 => $notes,
                'registered_by_user_id' => $user->id,
            ]);

            $remaining           = $amount;
            $allocated           = '0.00';
            $invoicesFullyPaid   = 0;

            foreach ($invoices as $invoice) {
                if (bccomp($remaining, '0', 2) <= 0) {
                    break;
                }

                $apply = $this->bcmin($remaining, (string) $invoice->balance);

                // Create individual Payment allocation.
                Payment::create([
                    'invoice_id'             => $invoice->id,
                    'customer_payment_id'    => $customerPayment->id,
                    'method'                 => $method,
                    'amount'                 => $apply,
                    'paid_at'               => now(),
                    'notes'                  => $notes,
                    'registered_by_user_id'  => $user->id,
                ]);

                // Update invoice totals.
                $newPaidAmount = bcadd((string) $invoice->paid_amount, $apply, 2);
                $newBalance    = bcsub((string) $invoice->total, $newPaidAmount, 2);
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

            // Any remainder becomes credit balance.
            $creditAdded = '0.00';
            if (bccomp($remaining, '0', 2) > 0) {
                $creditAdded = $remaining;
                $customer->credit_balance = bcadd((string) $customer->credit_balance, $remaining, 2);
                $customer->save();
            }

            return [
                'allocated'           => $allocated,
                'credit_added'        => $creditAdded,
                'invoices_fully_paid' => $invoicesFullyPaid,
            ];
        });
    }

    private function bcmin(string $a, string $b): string
    {
        return bccomp($a, $b, 2) <= 0 ? $a : $b;
    }
}
