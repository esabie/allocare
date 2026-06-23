<?php

namespace App\Models;

use App\Support\AuditTrail;
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
        'rescheduled_for',
        'notes',
        'source_mar_slug',
        'is_prn_dose',
        'prn_indication',
        'effectiveness_rating',
        'next_permissible_dose_at',
        'reason',
        'witness_user_id',
        'witness_name',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'rescheduled_for' => 'datetime',
        'is_prn_dose' => 'boolean',
        'next_permissible_dose_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MedicationAdministration $record) {
            AuditTrail::recordDeleteAttempt(
                'medication_administration',
                (string) $record->id,
                $record->medication?->name ?? 'Medication administration #'.$record->id,
                $record->only([
                    'patient_id',
                    'patient_medication_id',
                    'status',
                    'administered_at',
                    'scheduled_for',
                ]),
                request(),
                'Medication administration records are permanently retained and cannot be deleted.',
            );

            throw new \RuntimeException('Medication administration records are permanently retained and cannot be deleted.');
        });
    }

    public function scopeNotVoided($query)
    {
        return $query->whereNull('voided_at');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

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
