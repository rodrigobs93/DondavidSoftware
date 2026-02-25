<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use App\Services\EscPosTicketRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrintWorker extends Command
{
    protected $signature   = 'app:print-worker';
    protected $description = 'Daemon that processes queued print jobs and sends them to the thermal printer.';

    private EscPosTicketRenderer $renderer;
    private string $printerPort;

    public function handle(): void
    {
        $this->renderer    = new EscPosTicketRenderer();
        $this->printerPort = env('THERMAL_PRINTER_PORT', 'COM3');

        $this->info("Print worker started. Printer: {$this->printerPort}");
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

        $this->info("Processing job #{$job->id} (invoice #{$job->invoice_id}, attempt {$job->attempts})");

        try {
            $bytes = $this->renderer->render($job->payload);
            $this->writeToPort($bytes);

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

    private function writeToPort(string $bytes): void
    {
        $port = $this->printerPort;

        // On Windows, open the port as a file for raw ESC/POS output
        $handle = @fopen($port, 'wb');

        if ($handle === false) {
            // Fallback: try Windows COPY /B approach via shell
            $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'print_' . uniqid() . '.bin';
            file_put_contents($tmpFile, $bytes);
            $cmd = "COPY /B \"$tmpFile\" $port";
            exec($cmd, $output, $retval);
            @unlink($tmpFile);

            if ($retval !== 0) {
                throw new \RuntimeException("No se pudo escribir en el puerto {$port}. ¿Está la impresora conectada?");
            }
            return;
        }

        fwrite($handle, $bytes);
        fclose($handle);
    }
}
