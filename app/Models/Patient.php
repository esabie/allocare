<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    public const LIFECYCLE_ACTIVE = 'active';

    public const LIFECYCLE_INACTIVE = 'inactive';

    public const LIFECYCLE_FINISHED = 'finished';

    public const LIFECYCLE_STATUSES = [
        self::LIFECYCLE_ACTIVE,
        self::LIFECYCLE_INACTIVE,
        self::LIFECYCLE_FINISHED,
    ];

    protected $fillable = [
        'url_key',
        'slug',
        'name',
        'preferred_name',
        'reference',
        'nhs_number',
        'gp_name',
        'gp_practice',
        'gp_phone',
        'primary_language',
        'interpreter_required',
        'capacity_status',
        'best_interest_decision',
        'information_sharing_consent',
        'dols_lps_status',
        'dnacpr_status',
        'photo_path',
        'dob',
        'allergies',
        'allergy_details',
        'address',
        'latitude',
        'longitude',
        'phone',
        'status',
        'lifecycle_status',
        'rag_status',
        'news2_oxygen_scale',
        'staffing_ratio',
        'weight_kg',
        'height_m',
        'care_group',
        'service_start_date',
        'profile_completion_due_at',
        'profile_completed_at',
        'email',
        'mobility_aids',
        'hoist_type',
        'sling_size',
        'equipment_notes',
        'environmental_notes',
        'next_of_kin',
        'next_of_kin_tel',
        'next_of_kin_email',
        'other_relevant_people',
        'social_services_number',
        'social_worker_name',
        'social_worker_contact',
        'commissioner_name',
        'commissioner_contact',
        'emergency_contact_name',
        'emergency_contact_phone',
        'primary_diagnosis',
        'avatar',
        'care_plan_modules_initialized',
    ];

    protected $casts = [
        'allergies' => 'array',
        'allergy_details' => 'array',
        'interpreter_required' => 'boolean',
        'care_plan_modules_initialized' => 'boolean',
        'service_start_date' => 'date',
        'profile_completion_due_at' => 'datetime',
        'profile_completed_at' => 'datetime',
        'weight_kg' => 'decimal:2',
        'height_m' => 'decimal:2',
    ];

    public function normalizedLifecycleStatus(): string
    {
        $raw = strtolower(trim((string) ($this->lifecycle_status ?? self::LIFECYCLE_ACTIVE)));

        return in_array($raw, self::LIFECYCLE_STATUSES, true) ? $raw : self::LIFECYCLE_ACTIVE;
    }

    public function lifecycleStatusLabel(): string
    {
        return match ($this->normalizedLifecycleStatus()) {
            self::LIFECYCLE_INACTIVE => 'Inactive',
            self::LIFECYCLE_FINISHED => 'Finished / Discharged',
            default => 'Active',
        };
    }

    public function isRosterable(): bool
    {
        return $this->normalizedLifecycleStatus() === self::LIFECYCLE_ACTIVE;
    }

    public function isVisibleOnDashboard(): bool
    {
        return $this->normalizedLifecycleStatus() !== self::LIFECYCLE_FINISHED;
    }

    public function ragDisplayLabel(): string
    {
        $raw = $this->rag_status ?? $this->status ?? 'green';

        return match (strtolower(trim((string) $raw))) {
            'red' => 'RED',
            'amber' => 'AMBER',
            'green' => 'GREEN',
            default => 'GREEN',
        };
    }

    public function normalizedRagStatus(): string
    {
        return strtolower($this->ragDisplayLabel());
    }

    /** @param 'green'|'amber'|'red'|string $rag */
    public function syncRagStatus(string $rag): void
    {
        $normalized = match (strtolower(trim($rag))) {
            'red' => 'red',
            'amber' => 'amber',
            'green' => 'green',
            default => 'green',
        };

        $this->forceFill([
            'rag_status' => $normalized,
            'status' => strtoupper($normalized),
        ]);
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(PatientHandover::class)->orderByDesc('recorded_at');
    }

    public function woundAssessments(): HasMany
    {
        return $this->hasMany(PatientWoundAssessment::class)->orderByDesc('recorded_at');
    }

    public function fluidRecords(): HasMany
    {
        return $this->hasMany(PatientFluidRecord::class)->orderByDesc('recorded_at');
    }

    public function bowelRecords(): HasMany
    {
        return $this->hasMany(PatientBowelRecord::class)->orderByDesc('recorded_at');
    }

    public function privacyRequests(): HasMany
    {
        return $this->hasMany(PrivacyRequest::class);
    }

    public function careGroupVersions(): HasMany
    {
        return $this->hasMany(PatientCareGroupVersion::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(PatientUploadedDocument::class)->orderByDesc('created_at');
    }

    public function carePlanModules(): HasMany
    {
        return $this->hasMany(PatientCarePlanModule::class)->orderBy('sort_order');
    }
}

