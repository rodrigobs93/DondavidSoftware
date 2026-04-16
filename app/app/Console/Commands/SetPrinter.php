<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class SetPrinter extends Command
{
    protected $signature   = 'pos:set-printer {queue}';
    protected $description = 'Persist the Windows thermal printer queue name into settings (used by the Windows installer).';

    public function handle(): int
    {
        $queue = trim($this->argument('queue'));
        if ($queue === '') {
            $this->error('Queue name cannot be empty.');
            return self::FAILURE;
        }

        Setting::set('thermal_printer_name', $queue);
        $this->info("Printer queue set to: {$queue}");
        return self::SUCCESS;
    }
}
