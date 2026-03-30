<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->timestampTz('paid_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('customer_id');
            $table->index('paid_at');
        });

        DB::statement("ALTER TABLE customer_payments ADD CONSTRAINT customer_payments_method_check CHECK (method IN ('CASH','CARD','NEQUI','DAVIPLATA','BREB'))");
        DB::statement("ALTER TABLE customer_payments ADD CONSTRAINT customer_payments_amount_positive CHECK (amount > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
