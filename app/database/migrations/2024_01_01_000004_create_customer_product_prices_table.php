<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('price', 12, 2);
            $table->timestampsTz();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['customer_id', 'product_id'], 'uq_customer_product');
        });

        DB::statement("ALTER TABLE customer_product_prices ADD CONSTRAINT cpp_price_check CHECK (price >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_prices');
    }
};
