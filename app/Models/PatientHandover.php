<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientHandover extends Model
{
    public const SHIFT_DAY = 'day';

    public const SHIFT_NIGHT = 'night';

    protected $fillable = [
        'patient_id',
        'patient_schedule_id',
        'shift_type',
        'shift_date',
        'author_user_id',
        'presentation',
        'care_delivered',
        'medication_summary',
        'risks_changes',
        'handover_notes',
        'sleep_summary',
        'disturbances',
        'night_medications',
        'seizure_respiratory_events',
        'morning_priorities',
        'recorded_at',
        'controlled_drug_reconciliation_complete',
        'auto_generated',
        'auto_snapshot',
        'period_start_at',
        'period_end_at',
        'acknowledged_by_user_id',
        'acknowledged_at',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'recorded_at' => 'datetime',
        'controlled_drug_reconciliation_complete' => 'boolean',
        'auto_generated' => 'boolean',
        'auto_snapshot' => 'array',
        'period_start_at' => 'datetime',
        'period_end_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PatientSchedule::class, 'patient_schedule_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}
