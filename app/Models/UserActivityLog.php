<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class UserActivityLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Activity log entries are immutable and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Activity log entries are permanent and cannot be deleted.');
        });
    }

    protected $fillable = [
        'user_id',
        'user_name',
        'method',
        'path',
        'status',
        'duration_ms',
        'ip_address',
        'user_agent',
        'session_id',
        'device_type',
        'route_name',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
