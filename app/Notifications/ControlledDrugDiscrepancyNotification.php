<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ControlledDrugDiscrepancyNotification extends Notification
{
    public function __construct(
        public string $patientName,
        public string $medicationName,
        public float $expectedBalance,
        public float $countedBalance,
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
        $delta = round($this->countedBalance - $this->expectedBalance, 2);
        $direction = $delta > 0 ? 'over' : 'short';

        return [
            'type' => 'controlled_drug_discrepancy',
            'title' => "CD stock discrepancy — {$this->patientName}",
            'body' => "{$this->medicationName}: expected {$this->expectedBalance}, counted {$this->countedBalance} ({$direction} ".abs($delta).')',
            'href' => $this->href,
            'patient_name' => $this->patientName,
            'medication_name' => $this->medicationName,
            'expected_balance' => $this->expectedBalance,
            'counted_balance' => $this->countedBalance,
        ];
    }
}
