<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientCarePlanSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_slug',
        'plan_slug',
        'snapshot_id',
        'schema_version',
        'status',
        'submitted_at',
        'submitted_by_user_id',
        'updated_by_user_id',
        'review_due_at',
        'key_fields',
        'data_excerpt',
    ];

    protected $casts = [
        'schema_version' => 'integer',
        'submitted_at' => 'datetime',
        'review_due_at' => 'date',
        'key_fields' => 'array',
    ];
}
