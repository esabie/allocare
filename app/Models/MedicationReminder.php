<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'patient_medication_id',
        'due_at',
        'dismissed',
        'dismissed_by_user_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'dismissed' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by_user_id');
    }
}
