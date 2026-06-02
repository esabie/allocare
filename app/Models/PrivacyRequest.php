<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrivacyRequest extends Model
{
    public const TYPE_SUBJECT_ACCESS = 'subject_access';

    public const TYPE_ERASURE = 'erasure';

    public const TYPE_DATA_BREACH = 'data_breach';

    public const TYPES = [
        self::TYPE_SUBJECT_ACCESS,
        self::TYPE_ERASURE,
        self::TYPE_DATA_BREACH,
    ];

    public const STATUSES = ['pending', 'in_progress', 'completed', 'rejected'];

    protected $fillable = [
        'request_type',
        'status',
        'patient_id',
        'subject_name',
        'subject_email',
        'request_details',
        'outcome_notes',
        'requested_by_user_id',
        'handled_by_user_id',
        'due_at',
        'completed_at',
        'discovered_at',
        'ico_notification_required',
        'ico_notified_at',
        'individuals_affected_count',
        'breach_categories',
    ];

    protected $casts = [
        'due_at' => 'date',
        'completed_at' => 'datetime',
        'discovered_at' => 'datetime',
        'ico_notification_required' => 'boolean',
        'ico_notified_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function erasureJob(): HasOne
    {
        return $this->hasOne(PrivacyErasureJob::class);
    }
}
