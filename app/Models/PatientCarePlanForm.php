<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientCarePlanForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_slug',
        'plan_slug',
        'data',
        'schema_version',
        'status',
        'submitted_at',
        'submitted_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'data' => 'array',
        'schema_version' => 'integer',
        'submitted_at' => 'datetime',
    ];
}

