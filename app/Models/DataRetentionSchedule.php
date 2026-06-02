<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataRetentionSchedule extends Model
{
    protected $fillable = [
        'data_category',
        'retention_period',
        'legal_basis',
        'review_cycle_months',
        'last_reviewed_at',
        'notes',
        'updated_by_user_id',
    ];

    protected $casts = [
        'last_reviewed_at' => 'date',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
