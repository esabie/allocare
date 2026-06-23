<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessMedicationEscalations extends Command
{
    protected $signature = 'medications:process-escalations';

    protected $description = 'Detect missed medications, time-critical escalations, and ensure today\'s reminders exist';

    public function handle(): int
    {
        if (! function_exists('process_medication_escalations')) {
            $this->error('Medication escalation helpers unavailable. Ensure routes are loaded.');

            return self::FAILURE;
        }

        $processed = process_medication_escalations();
        $this->info("Processed {$processed} medication escalation(s).");

        Cache::put('medication_escalations_last_run', now(), 120);

        return self::SUCCESS;
    }
}
