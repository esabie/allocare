<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ForcePasswordResetNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class ForcePasswordResetCommand extends Command
{
    protected $signature = 'users:force-password-reset
                            {--dry-run : Flag users without sending emails}';

    protected $description = 'Require all users to reset their password and email them a reset link';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $broker = Password::broker();
        $sent = 0;
        $failed = 0;
        $flagged = 0;

        User::query()->orderBy('id')->chunkById(100, function ($users) use ($broker, $dryRun, &$sent, &$failed, &$flagged) {
            foreach ($users as $user) {
                $user->forceFill(['must_reset_password' => true])->save();
                $flagged++;

                if ($dryRun) {
                    continue;
                }

                try {
                    $token = $broker->createToken($user);
                    $user->notify(new ForcePasswordResetNotification($token));
                    $sent++;
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Failed to send force password reset email', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'message' => $exception->getMessage(),
                    ]);
                    $this->error("Failed for {$user->email}: {$exception->getMessage()}");
                }
            }
        });

        if ($dryRun) {
            $this->info("Dry run complete. Flagged {$flagged} user(s); no emails sent.");
        } else {
            $this->info("Flagged {$flagged} user(s). Sent {$sent} email(s); {$failed} failure(s).");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
