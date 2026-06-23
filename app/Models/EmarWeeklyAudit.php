<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmarWeeklyAudit extends Model
{
    protected $fillable = [
        'week_start',
        'week_end',
        'reviewed_by_user_id',
        'signed_at',
        'notes',
        'checklist',
        'summary',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'signed_at' => 'datetime',
        'checklist' => 'array',
        'summary' => 'array',
    ];

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
