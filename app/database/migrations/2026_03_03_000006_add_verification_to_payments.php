<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('notes');
            $table->timestampTz('verified_at')->nullable()->after('verified');
            $table->foreignId('verified_by_user_id')
                  ->nullable()
                  ->after('verified_at')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestampTz('updated_at')->nullable()->after('created_at');

            // Composite index: fast query for "unverified + ordered by date"
            $table->index(['verified', 'paid_at'], 'idx_payments_verified_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_verified_paid_at');
            $table->dropForeign(['verified_by_user_id']);
            $table->dropColumn(['verified', 'verified_at', 'verified_by_user_id', 'updated_at']);
        });
    }
};
