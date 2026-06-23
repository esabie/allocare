<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Audit log entries are immutable and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Audit log entries are permanent and cannot be deleted.');
        });
    }

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_key',
        'subject_label',
        'description',
        'changes',
        'previous_values',
        'new_values',
        'request_method',
        'request_path',
        'http_status',
        'ip_address',
        'user_agent',
        'session_id',
        'device_type',
        'metadata',
        'integrity_hash',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'previous_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
