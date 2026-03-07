<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use App\Services\EscPosTicketRenderer;
use App\Services\ThermalPrinterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrintWorker extends Command
{
    protected $signature   = 'app:print-worker';
    protected $description = 'Daemon that processes queued print jobs and sends them to the thermal printer.';

    private EscPosTicketRenderer $renderer;
    private ThermalPrinterService $printer;

    public function handle(): void
    {
        $this->renderer = new EscPosTicketRenderer();
        $this->printer  = new ThermalPrinterService();

        $this->info("Print worker started. Printer: {$this->printer->printerName()}");
        $this->info("Polling every 2 seconds. Press Ctrl+C to stop.");

        // On startup: reset any stuck PRINTING jobs back to QUEUED
        $stuck = PrintJob::where('status', 'PRINTING')->count();
        if ($stuck > 0) {
            PrintJob::where('status', 'PRINTING')->update(['status' => 'QUEUED']);
            $this->warn("Reset {$stuck} stuck PRINTING job(s) back to QUEUED.");
        }

        while (true) {
            $this->processNext();
            sleep(2);
        }
    }

    private function processNext(): void
    {
        // Claim the next queued job atomically
        $job = DB::transaction(function () {
            $job = PrintJob::where('status', 'QUEUED')
                ->orderBy('queued_at')
                ->lockForUpdate()
                ->first();

            if ($job) {
                $job->update([
                    'status'   => 'PRINTING',
                    'attempts' => $job->attempts + 1,
                ]);
            }

            return $job;
        });

        if (!$job) {
            return;
        }

        $source = $job->invoice_id ? "factura #{$job->invoice_id}" : "recibo #{$job->quick_sale_id}";
        $this->info("Processing job #{$job->id} ({$source}, attempt {$job->attempts})");

        try {
            $type  = $job->payload['type'] ?? 'invoice';
            $bytes = $type === 'quick_sale'
                ? $this->renderer->renderQuickSale($job->payload)
                : $this->renderer->render($job->payload);
            $this->printer->send($bytes);

            $job->update([
                'status'     => 'PRINTED',
                'printed_at' => now(),
            ]);

            $this->info("Job #{$job->id} printed successfully.");
        } catch (\Throwable $e) {
            $this->error("Job #{$job->id} failed: " . $e->getMessage());

            $newStatus = $job->attempts >= 3 ? 'FAILED' : 'QUEUED';
            $job->update([
                'status'        => $newStatus,
                'error_message' => $e->getMessage(),
            ]);

            if ($newStatus === 'FAILED') {
                $this->error("Job #{$job->id} permanently FAILED after {$job->attempts} attempts.");
            } else {
                $this->warn("Job #{$job->id} will retry ({$job->attempts}/3).");
            }
        }
    }

}
