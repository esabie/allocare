<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientRiskAssessment extends Model
{
    public const LEVELS = ['low', 'moderate', 'high'];

    public const STATUSES = ['draft', 'active', 'archived'];

    protected $fillable = [
        'patient_id',
        'risk_slug',
        'risk_level',
        'status',
        'triggers',
        'current_controls',
        'mitigation_plan',
        'owner_name',
        'last_reviewed_at',
        'next_review_due_at',
        'review_cycle_months',
        'reviewed_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'last_reviewed_at' => 'date',
        'next_review_due_at' => 'date',
        'review_cycle_months' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PatientRiskAssessmentVersion::class)->orderByDesc('recorded_at');
    }
}
