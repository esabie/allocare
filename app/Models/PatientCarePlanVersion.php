<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCarePlanVersion extends Model
{
    protected $fillable = [
        'patient_slug',
        'plan_slug',
        'version_number',
        'data',
        'schema_version',
        'status',
        'review_due_at',
        'change_summary',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected $casts = [
        'data' => 'array',
        'schema_version' => 'integer',
        'review_due_at' => 'date',
        'recorded_at' => 'datetime',
    ];

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
