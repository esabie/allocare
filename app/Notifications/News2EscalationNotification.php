<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class News2EscalationNotification extends Notification
{
    public function __construct(
        public string $patientName,
        public int $news2Score,
        public string $riskLevel,
        public string $riskLabel,
        public string $guidance,
        public ?string $href = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $priority = match ($this->riskLevel) {
            'high' => 'HIGH PRIORITY',
            'medium' => 'URGENT',
            default => 'REVIEW',
        };

        return [
            'type' => 'news2_escalation',
            'title' => "{$priority} NEWS2 — {$this->patientName}",
            'body' => "Score {$this->news2Score} ({$this->riskLabel}). {$this->guidance}",
            'href' => $this->href,
            'patient_name' => $this->patientName,
            'news2_score' => $this->news2Score,
            'risk_level' => $this->riskLevel,
        ];
    }
}
