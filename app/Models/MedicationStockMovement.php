<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationStockMovement extends Model
{
    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ADMINISTRATION = 'administration';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_DESTRUCTION = 'destruction';

    public const TYPE_RECONCILIATION = 'reconciliation';

    protected $fillable = [
        'patient_medication_id',
        'recorded_by_user_id',
        'witness_user_id',
        'movement_type',
        'quantity_delta',
        'balance_after',
        'expected_balance',
        'counted_balance',
        'reference',
        'notes',
        'medication_administration_id',
        'patient_handover_id',
        'is_permanent_record',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'is_permanent_record' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MedicationStockMovement $movement) {
            if ($movement->is_permanent_record) {
                throw new \RuntimeException('Controlled drug destruction records cannot be deleted.');
            }
        });
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_user_id');
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(PatientHandover::class, 'patient_handover_id');
    }
}
