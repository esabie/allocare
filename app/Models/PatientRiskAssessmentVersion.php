<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientRiskAssessmentVersion extends Model
{
    protected $fillable = [
        'patient_risk_assessment_id',
        'patient_id',
        'risk_slug',
        'snapshot',
        'change_summary',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PatientRiskAssessment::class, 'patient_risk_assessment_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
