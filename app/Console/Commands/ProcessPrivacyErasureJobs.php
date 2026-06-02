<?php

namespace App\Console\Commands;

use App\Models\PrivacyErasureJob;
use Illuminate\Console\Command;

class ProcessPrivacyErasureJobs extends Command
{
    protected $signature = 'privacy:process-erasure-jobs {--limit=25 : Maximum jobs to process}';

    protected $description = 'Process pending GDPR erasure jobs (anonymise linked patient records)';

    public function handle(): int
    {
        if (!function_exists('process_privacy_erasure_job')) {
            $this->error('Privacy erasure helpers unavailable. Ensure routes are loaded.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $jobs = PrivacyErasureJob::query()
            ->where('status', PrivacyErasureJob::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No pending erasure jobs.');

            return self::SUCCESS;
        }

        $completed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $ok = process_privacy_erasure_job($job);
            if ($ok) {
                $completed++;
                $this->line("Completed erasure job #{$job->id}");
            } else {
                $failed++;
                $this->error("Failed erasure job #{$job->id}: ".$job->fresh()->error_message);
            }
        }

        $this->info("Processed {$jobs->count()} job(s): {$completed} completed, {$failed} failed.");

        return self::SUCCESS;
    }
}
