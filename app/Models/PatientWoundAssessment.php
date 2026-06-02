<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientWoundAssessment extends Model
{
    public const BODY_MAP_REGIONS = [
        'head_front',
        'chest_front',
        'abdomen_front',
        'left_arm_front',
        'right_arm_front',
        'left_leg_front',
        'right_leg_front',
        'head_back',
        'upper_back',
        'lower_back',
        'left_arm_back',
        'right_arm_back',
        'left_leg_back',
        'right_leg_back',
        'sacrum',
        'left_heel',
        'right_heel',
    ];

    public const PRESSURE_GRADES = [
        'category_1',
        'category_2',
        'category_3',
        'category_4',
        'unstageable',
        'deep_tissue_injury',
    ];

    protected $fillable = [
        'patient_id',
        'recorded_by_user_id',
        'recorded_at',
        'wound_site',
        'wound_type',
        'pressure_ulcer_grade',
        'length_cm',
        'width_cm',
        'depth_cm',
        'exudate',
        'periwound_condition',
        'pain_score',
        'dressing_type',
        'pressure_regime',
        'infection_signs',
        'escalation_required',
        'body_map_notes',
        'body_map_region',
        'photo_path',
        'review_due_at',
        'plan_actions',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'review_due_at' => 'date',
        'escalation_required' => 'boolean',
        'length_cm' => 'float',
        'width_cm' => 'float',
        'depth_cm' => 'float',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
