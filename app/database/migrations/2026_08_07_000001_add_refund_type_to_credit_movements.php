<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Editing an invoice can lower its total below what was already paid; the
     * excess is refunded to the customer's credit_balance and documented with a
     * REFUND_FROM_EDIT ledger entry. Widen the type CHECK to allow it.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // sqlite has no CHECK constraint from the original migration
        }

        DB::statement("ALTER TABLE credit_movements DROP CONSTRAINT IF EXISTS credit_movements_type_check");
        DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_type_check CHECK (type IN ('APPLIED_TO_INVOICE','REFUND_FROM_EDIT'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE credit_movements DROP CONSTRAINT IF EXISTS credit_movements_type_check");
        DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_type_check CHECK (type IN ('APPLIED_TO_INVOICE'))");
    }
};
