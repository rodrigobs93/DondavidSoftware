<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('doc_type', 10)->nullable();
            $table->string('doc_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_generic')->default(false);
            $table->boolean('requires_fe')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_doc_type_check CHECK (doc_type IN ('NIT', 'CC') OR doc_type IS NULL)");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT uq_customers_doc UNIQUE (doc_type, doc_number)");
        DB::statement("CREATE UNIQUE INDEX uq_customers_generic ON customers (is_generic) WHERE is_generic = TRUE");
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
