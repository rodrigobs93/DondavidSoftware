<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default delivery fee ("domicilio") charged to this customer. NULL means
        // "no domicilio configured" — distinct from 0 (charged, but free). The value
        // is only a template: on sale it is copied into invoices.delivery_fee, so
        // changing it later never alters previously issued invoices.
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('default_delivery_fee', 12, 2)->nullable()->default(null)->after('credit_balance');
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_default_delivery_fee_non_negative CHECK (default_delivery_fee IS NULL OR default_delivery_fee >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_default_delivery_fee_non_negative");

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('default_delivery_fee');
        });
    }
};
