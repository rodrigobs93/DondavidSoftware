<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('invoice_number', 50)->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->string('status', 10)->default('PENDING');
            $table->text('notes')->nullable();
            $table->boolean('voided')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestampsTz();

            $table->foreign('supplier_id')->references('id')->on('suppliers');
        });

        DB::statement("ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_status_check CHECK (status IN ('PAID','PARTIAL','PENDING'))");
        DB::statement("ALTER TABLE supplier_invoices ADD FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        DB::statement("CREATE INDEX idx_supplier_invoices_supplier_id ON supplier_invoices (supplier_id)");
        DB::statement("CREATE INDEX idx_supplier_invoices_date ON supplier_invoices (invoice_date)");
        DB::statement("CREATE INDEX idx_supplier_invoices_balance ON supplier_invoices (balance) WHERE balance > 0");
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
