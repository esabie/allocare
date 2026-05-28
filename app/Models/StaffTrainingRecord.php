<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTrainingRecord extends Model
{
    protected $fillable = [
        'user_id',
        'course_name',
        'provider',
        'completed_date',
        'expiry_date',
        'certificate_reference',
        'status',
    ];

    protected $casts = [
        'completed_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
