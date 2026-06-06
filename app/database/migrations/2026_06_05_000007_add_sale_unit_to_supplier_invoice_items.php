<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->string('sale_unit', 10)->default('UNIT')->after('description');
        });

        DB::statement("ALTER TABLE supplier_invoice_items ADD CONSTRAINT supplier_invoice_items_sale_unit_check CHECK (sale_unit IN ('KG','UNIT'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE supplier_invoice_items DROP CONSTRAINT IF EXISTS supplier_invoice_items_sale_unit_check");
        Schema::table('supplier_invoice_items', function (Blueprint $table) {
            $table->dropColumn('sale_unit');
        });
    }
};
