<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class MedicationManagerAlertNotification extends Notification
{
    public function __construct(
        public string $patientName,
        public string $medicationName,
        public string $status,
        public string $reason,
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
        $statusLabel = strtoupper(str_replace('_', ' ', $this->status));

        return [
            'type' => 'medication_outcome',
            'title' => "{$statusLabel} medication — {$this->patientName}",
            'body' => trim($this->medicationName.($this->reason !== '' ? ' — '.$this->reason : '')),
            'href' => $this->href,
            'patient_name' => $this->patientName,
            'medication_name' => $this->medicationName,
            'status' => $this->status,
        ];
    }
}
