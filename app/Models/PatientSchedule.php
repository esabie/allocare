<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'assigned_user_id',
        'start_at',
        'end_at',
        'checked_in_at',
        'checked_out_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_distance_metres',
        'check_out_distance_metres',
        'late_by_minutes',
        'left_early_by_minutes',
        'purpose',
        'notes',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function visitTasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScheduleVisitTask::class, 'patient_schedule_id')->orderBy('sort_order');
    }
}

