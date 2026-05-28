<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCompetency extends Model
{
    protected $fillable = [
        'user_id',
        'skill_name',
        'level',
        'assessed_date',
        'next_review_date',
        'assessed_by',
        'notes',
        'status',
    ];

    protected $casts = [
        'assessed_date' => 'date',
        'next_review_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
