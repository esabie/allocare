<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientVital extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'heart_rate',
        'bp_systolic',
        'bp_diastolic',
        'spo2',
        'temperature_celsius',
        'blood_glucose_mmol',
        'weight_kg',
        'pain_score',
        'other_observation',
        'recorded_at',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature_celsius' => 'decimal:1',
        'blood_glucose_mmol' => 'decimal:2',
        'weight_kg' => 'decimal:2',
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

