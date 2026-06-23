<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientRiskAssessment extends Model
{
    public const LEVELS = ['green', 'amber', 'red'];

    public const LEVEL_LABELS = [
        'green' => 'Green',
        'amber' => 'Amber',
        'red' => 'Red',
    ];

    /** @var array<string, string> */
    public const LEGACY_LEVEL_MAP = [
        'low' => 'green',
        'moderate' => 'amber',
        'high' => 'red',
    ];

    public const STATUSES = ['draft', 'active', 'archived'];

    protected $fillable = [
        'patient_id',
        'risk_slug',
        'risk_level',
        'status',
        'risk_statement',
        'triggers',
        'proactive_controls',
        'active_controls',
        'reactive_controls',
        'monitoring_requirements',
        'escalation_pathway',
        'capacity_consent_notes',
        'legal_restrictions',
        'linked_care_plan_slugs',
        'linked_incident_ids',
        'current_controls',
        'mitigation_plan',
        'owner_name',
        'last_reviewed_at',
        'next_review_due_at',
        'review_cycle_months',
        'reviewed_by_user_id',
        'updated_by_user_id',
    ];

    public static function normalizeLevel(?string $level): ?string
    {
        if ($level === null) {
            return null;
        }

        $normalized = strtolower(trim($level));

        if (isset(self::LEGACY_LEVEL_MAP[$normalized])) {
            return self::LEGACY_LEVEL_MAP[$normalized];
        }

        return in_array($normalized, self::LEVELS, true) ? $normalized : null;
    }

    public static function levelLabel(?string $level): ?string
    {
        $normalized = self::normalizeLevel($level);

        return $normalized ? (self::LEVEL_LABELS[$normalized] ?? null) : null;
    }

    protected $casts = [
        'last_reviewed_at' => 'date',
        'next_review_due_at' => 'date',
        'review_cycle_months' => 'integer',
        'linked_care_plan_slugs' => 'array',
        'linked_incident_ids' => 'array',
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
