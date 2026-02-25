<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name_snapshot', 150);
            $table->string('sale_unit_snapshot', 10);
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->smallInteger('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });

        DB::statement("ALTER TABLE invoice_items ADD CONSTRAINT ii_unit_check CHECK (sale_unit_snapshot IN ('KG','UNIT'))");
        DB::statement("ALTER TABLE invoice_items ADD CONSTRAINT ii_qty_check CHECK (quantity > 0)");
        DB::statement("ALTER TABLE invoice_items ADD CONSTRAINT ii_price_check CHECK (unit_price >= 0)");
        DB::statement("CREATE INDEX idx_invoice_items_invoice ON invoice_items (invoice_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
