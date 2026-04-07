<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('type', 40)->default('APPLIED_TO_INVOICE');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('customer_id');
            $table->index('invoice_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_amount_check CHECK (amount > 0)");
            DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT credit_movements_type_check CHECK (type IN ('APPLIED_TO_INVOICE'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_movements');
    }
};
