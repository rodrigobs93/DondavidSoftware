<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });

        // Case-insensitive unique index (PostgreSQL lower())
        DB::statement('CREATE UNIQUE INDEX uq_product_categories_name ON product_categories (lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
