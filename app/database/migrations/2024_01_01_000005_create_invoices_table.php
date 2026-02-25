<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE SEQUENCE IF NOT EXISTS invoice_consecutive_seq START 1 INCREMENT 1 NO CYCLE");

        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('consecutive', 7)->unique();
            $table->integer('consecutive_int')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('created_by_user_id');
            $table->date('invoice_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->string('status', 10)->default('PENDING');
            $table->boolean('requires_fe')->default(false);
            $table->string('fe_status', 20)->default('NONE');
            $table->string('fe_reference', 100)->nullable();
            $table->timestampTz('fe_issued_at')->nullable();
            $table->unsignedBigInteger('fe_issued_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('voided')->default(false);
            $table->timestampTz('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by_user_id')->nullable();
            $table->timestampsTz();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('PAID','PARTIAL','PENDING'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_fe_status_check CHECK (fe_status IN ('NONE','PENDING','ISSUED'))");
        DB::statement("ALTER TABLE invoices ADD FOREIGN KEY (fe_issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        DB::statement("ALTER TABLE invoices ADD FOREIGN KEY (voided_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        DB::statement("CREATE INDEX idx_invoices_status ON invoices (status)");
        DB::statement("CREATE INDEX idx_invoices_customer_id ON invoices (customer_id)");
        DB::statement("CREATE INDEX idx_invoices_date ON invoices (invoice_date)");
        DB::statement("CREATE INDEX idx_invoices_fe_pending ON invoices (fe_status) WHERE fe_status = 'PENDING'");
        DB::statement("CREATE INDEX idx_invoices_balance ON invoices (balance) WHERE balance > 0");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        DB::statement("DROP SEQUENCE IF EXISTS invoice_consecutive_seq");
    }
};
