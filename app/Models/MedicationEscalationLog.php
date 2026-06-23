<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationEscalationLog extends Model
{
    public const TYPE_MISSED = 'missed';

    public const TYPE_TIME_CRITICAL_MISSED = 'time_critical_missed';

    public const TYPE_PRN_OVERUSE = 'prn_overuse';

    public const TYPE_PRN_OVERUSE_BLOCKED = 'prn_overuse_blocked';

    public const TYPE_RESCUE_ADMINISTRATION = 'rescue_administration';

    protected $fillable = [
        'patient_id',
        'patient_medication_id',
        'medication_administration_id',
        'escalation_type',
        'slot_due_at',
        'metadata',
    ];

    protected $casts = [
        'slot_due_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function administration(): BelongsTo
    {
        return $this->belongsTo(MedicationAdministration::class, 'medication_administration_id');
    }
}
