<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyErasureJob extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'privacy_request_id',
        'patient_id',
        'status',
        'scheduled_at',
        'processed_at',
        'result_summary',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function privacyRequest(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
