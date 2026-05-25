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
        'spo2',
        'other_observation',
        'recorded_at',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}

