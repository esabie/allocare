<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientBowelRecord extends Model
{
    public const BRISTOL_LABELS = [
        1 => 'Type 1 — separate hard lumps',
        2 => 'Type 2 — lumpy sausage',
        3 => 'Type 3 — cracked sausage',
        4 => 'Type 4 — smooth sausage',
        5 => 'Type 5 — soft blobs',
        6 => 'Type 6 — mushy',
        7 => 'Type 7 — liquid',
    ];

    protected $fillable = [
        'patient_id',
        'recorded_by_user_id',
        'recorded_at',
        'bowel_opened',
        'bristol_type',
        'continence_status',
        'notes',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'bowel_opened' => 'boolean',
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
