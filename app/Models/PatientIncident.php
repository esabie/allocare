<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PatientIncident extends Model
{
    protected $fillable = [
        'patient_id',
        'reported_by_user_id',
        'reference',
        'incident_title',
        'incident_date',
        'incident_time',
        'location',
        'data',
        'submitted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'incident_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function investigation(): HasOne
    {
        return $this->hasOne(IncidentInvestigation::class);
    }
}
