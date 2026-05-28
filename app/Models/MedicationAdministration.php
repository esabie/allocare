<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'patient_medication_id',
        'administered_by_user_id',
        'status',
        'administered_at',
        'scheduled_for',
        'notes',
        'source_mar_slug',
        'reason',
        'witness_user_id',
        'witness_name',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
        'scheduled_for' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by_user_id');
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_user_id');
    }
}
