<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCarePlanModule extends Model
{
    protected $fillable = [
        'patient_id',
        'module_slug',
        'custom_title',
        'purpose',
        'is_bespoke',
        'sort_order',
        'activated_by_user_id',
        'activated_at',
    ];

    protected $casts = [
        'is_bespoke' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }
}
