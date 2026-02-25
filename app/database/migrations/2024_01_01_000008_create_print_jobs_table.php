<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('status', 20)->default('QUEUED');
            $table->jsonb('payload');
            $table->smallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestampTz('queued_at')->useCurrent();
            $table->timestampTz('printed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });

        DB::statement("ALTER TABLE print_jobs ADD CONSTRAINT pj_status_check CHECK (status IN ('QUEUED','PRINTING','PRINTED','FAILED'))");
        DB::statement("CREATE INDEX idx_print_jobs_queued ON print_jobs (status) WHERE status = 'QUEUED'");
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
