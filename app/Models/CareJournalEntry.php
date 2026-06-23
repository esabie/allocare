<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareJournalEntry extends Model
{
    protected $fillable = [
        'patient_id',
        'author_user_id',
        'amended_by_user_id',
        'body',
        'template_slug',
        'structured_data',
        'outcome_status',
        'linked_care_plan_slug',
        'linked_support_objective',
        'linked_risk_assessment_slug',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'structured_data' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by_user_id');
    }
}
