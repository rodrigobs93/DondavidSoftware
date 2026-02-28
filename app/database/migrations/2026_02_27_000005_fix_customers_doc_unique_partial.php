<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the full unique constraint (blocks re-creating soft-deleted docs)
        DB::statement('ALTER TABLE customers DROP CONSTRAINT uq_customers_doc');

        // Replace with a partial unique index that only covers live (non-deleted) rows
        DB::statement('CREATE UNIQUE INDEX uq_customers_doc ON customers (doc_type, doc_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_customers_doc');
        DB::statement('ALTER TABLE customers ADD CONSTRAINT uq_customers_doc UNIQUE (doc_type, doc_number)');
    }
};
