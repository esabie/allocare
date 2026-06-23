<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class MedicationEscalationNotification extends Notification
{
    public function __construct(
        public string $escalationType,
        public string $title,
        public string $body,
        public ?string $href = null,
        public ?string $patientName = null,
        public ?string $medicationName = null,
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
        return [
            'type' => 'medication_escalation',
            'escalation_type' => $this->escalationType,
            'title' => $this->title,
            'body' => $this->body,
            'href' => $this->href,
            'patient_name' => $this->patientName,
            'medication_name' => $this->medicationName,
        ];
    }
}
