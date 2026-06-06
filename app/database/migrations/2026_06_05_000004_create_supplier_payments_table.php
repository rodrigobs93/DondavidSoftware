<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('supplier_invoice_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->timestampTz('paid_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('registered_by_user_id')->nullable();
            $table->string('submission_key', 64)->nullable()->unique();
            $table->timestampsTz();

            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices');
        });

        DB::statement("ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_method_check CHECK (method IN ('CASH','NEQUI','DAVIPLATA','DAVIVIENDA','OTHER'))");
        DB::statement("ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_amount_positive CHECK (amount > 0)");
        DB::statement("ALTER TABLE supplier_payments ADD FOREIGN KEY (registered_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        DB::statement("CREATE INDEX idx_supplier_payments_supplier_id ON supplier_payments (supplier_id)");
        DB::statement("CREATE INDEX idx_supplier_payments_paid_at ON supplier_payments (paid_at)");
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
