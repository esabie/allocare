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
        'movement_type',
        'quantity_delta',
        'balance_after',
        'reference',
        'notes',
        'medication_administration_id',
    ];

    protected $casts = [
        'quantity_delta' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function medication(): BelongsTo
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
