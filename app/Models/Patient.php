<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

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
        'rag_status',
        'staffing_ratio',
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
    ];

    protected $casts = [
        'allergies' => 'array',
        'allergy_details' => 'array',
        'interpreter_required' => 'boolean',
    ];

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
}

