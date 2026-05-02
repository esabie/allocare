<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'is_prn' => 'boolean',
        'active' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

