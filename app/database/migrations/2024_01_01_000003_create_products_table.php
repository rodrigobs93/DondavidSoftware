<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('sale_unit', 10);
            $table->decimal('base_price', 12, 2);
            $table->boolean('active')->default(true);
            $table->timestampTz('price_updated_at')->nullable();
            $table->unsignedBigInteger('price_updated_by_user_id')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_sale_unit_check CHECK (sale_unit IN ('KG', 'UNIT'))");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_base_price_check CHECK (base_price >= 0)");
        DB::statement("ALTER TABLE products ADD FOREIGN KEY (price_updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        DB::statement("CREATE INDEX idx_products_active ON products (active)");
        DB::statement("CREATE INDEX idx_products_name ON products (name)");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
