<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientVital extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'heart_rate',
        'bp_systolic',
        'spo2',
        'recorded_at',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}

