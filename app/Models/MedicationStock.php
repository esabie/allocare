<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicationStock extends Model
{
    protected $fillable = [
        'patient_medication_id',
        'balance',
        'unit',
        'reconciled_at',
        'reconciled_by_user_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function medication(): BelongsTo
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MedicationStockMovement::class, 'patient_medication_id', 'patient_medication_id');
    }
}
