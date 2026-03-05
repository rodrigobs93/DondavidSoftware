<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── payments ──────────────────────────────────────────────────────────
        Schema::table('payments', function (Blueprint $table) {
            // invoice_id must become nullable so quick-sale payments can exist without an invoice
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            $table->unsignedBigInteger('quick_sale_id')->nullable()->after('invoice_id');
            $table->foreign('quick_sale_id')->references('id')->on('quick_sales')->onDelete('cascade');
        });

        // Exactly one source: either invoice or quick_sale (not both, not neither)
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_source_check CHECK (
            (invoice_id IS NOT NULL AND quick_sale_id IS NULL) OR
            (invoice_id IS NULL     AND quick_sale_id IS NOT NULL)
        )");

        // ── print_jobs ────────────────────────────────────────────────────────
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            $table->unsignedBigInteger('quick_sale_id')->nullable()->after('invoice_id');
            $table->foreign('quick_sale_id')->references('id')->on('quick_sales')->onDelete('cascade');
        });

        DB::statement("ALTER TABLE print_jobs ADD CONSTRAINT print_jobs_source_check CHECK (
            (invoice_id IS NOT NULL AND quick_sale_id IS NULL) OR
            (invoice_id IS NULL     AND quick_sale_id IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['quick_sale_id']);
            $table->dropColumn('quick_sale_id');
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
        });
        DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_source_check");

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['quick_sale_id']);
            $table->dropColumn('quick_sale_id');
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
        });
        DB::statement("ALTER TABLE print_jobs DROP CONSTRAINT IF EXISTS print_jobs_source_check");
    }
};
