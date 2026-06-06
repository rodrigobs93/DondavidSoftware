<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_payment_id');
            $table->unsignedBigInteger('supplier_invoice_id');
            $table->decimal('allocated_amount', 12, 2);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('supplier_payment_id')->references('id')->on('supplier_payments')->cascadeOnDelete();
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices');
            $table->index('supplier_payment_id');
            $table->index('supplier_invoice_id');
        });

        DB::statement("ALTER TABLE supplier_payment_allocations ADD CONSTRAINT supplier_payment_allocations_amount_positive CHECK (allocated_amount > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};
