<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientFluidRecord extends Model
{
    protected $fillable = [
        'patient_id',
        'recorded_by_user_id',
        'recorded_at',
        'fluid_intake_ml',
        'fluid_output_ml',
        'fluid_type',
        'notes',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
