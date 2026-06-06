<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('tax_id', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('contact', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->decimal('credit_balance', 14, 2)->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_credit_balance_non_negative CHECK (credit_balance >= 0)");
        DB::statement("CREATE INDEX idx_suppliers_name ON suppliers (name)");
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
