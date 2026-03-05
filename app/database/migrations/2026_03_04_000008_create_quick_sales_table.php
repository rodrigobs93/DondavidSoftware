<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE SEQUENCE IF NOT EXISTS receipt_consecutive_seq START 1 INCREMENT 1 NO CYCLE");

        Schema::create('quick_sales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('receipt_number', 10)->unique(); // R-000001
            $table->integer('receipt_int')->unique();
            $table->date('sale_date');
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method', 10);           // CASH|CARD|NEQUI|DAVIPLATA|BREB
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->string('submission_key', 64)->nullable()->unique();
            $table->timestampsTz();

            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        DB::statement("ALTER TABLE quick_sales ADD CONSTRAINT quick_sales_method_check CHECK (payment_method IN ('CASH','CARD','NEQUI','DAVIPLATA','BREB'))");
        DB::statement("ALTER TABLE quick_sales ADD CONSTRAINT quick_sales_total_check CHECK (total_amount > 0)");

        DB::statement("CREATE INDEX idx_quick_sales_date ON quick_sales (sale_date)");
        DB::statement("CREATE INDEX idx_quick_sales_method ON quick_sales (payment_method)");
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_sales');
        DB::statement("DROP SEQUENCE IF EXISTS receipt_consecutive_seq");
    }
};
