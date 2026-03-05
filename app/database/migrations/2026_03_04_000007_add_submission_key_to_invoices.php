<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Client-generated idempotency key (UUID).
            // Unique constraint prevents a second INSERT from a duplicate request.
            $table->string('submission_key', 64)->nullable()->unique()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('submission_key');
        });
    }
};
