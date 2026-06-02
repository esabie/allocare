<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientMedication extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'name',
        'route',
        'dose',
        'scheduled_time',
        'is_prn',
        'active',
        'created_by_user_id',
        'frequency',
        'scheduled_times',
        'start_date',
        'end_date',
        'is_controlled',
        'prn_indication',
        'prn_max_daily_doses',
    ];

    protected $casts = [
        'is_prn' => 'boolean',
        'active' => 'boolean',
        'is_controlled' => 'boolean',
        'scheduled_times' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class, 'patient_medication_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(MedicationReminder::class, 'patient_medication_id');
    }

    public function stock(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MedicationStock::class, 'patient_medication_id');
    }
}
