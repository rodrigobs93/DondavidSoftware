<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('notes');
            $table->timestampTz('verified_at')->nullable()->after('verified');
            $table->foreignId('verified_by_user_id')
                  ->nullable()
                  ->after('verified_at')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['verified', 'verified_at']);
        });
    }
};
