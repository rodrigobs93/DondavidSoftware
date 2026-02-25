<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->timestampTz('paid_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('registered_by_user_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('CASH','CARD','NEQUI','DAVIPLATA','BREB'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)");
        DB::statement("ALTER TABLE payments ADD FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        DB::statement("CREATE INDEX idx_payments_invoice ON payments (invoice_id)");
        DB::statement("CREATE INDEX idx_payments_method ON payments (method)");
        DB::statement("CREATE INDEX idx_payments_paid_at ON payments (paid_at)");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
