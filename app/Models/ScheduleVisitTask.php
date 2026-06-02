<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleVisitTask extends Model
{
    public const OUTCOMES = ['completed', 'refused', 'unable', 'escalated'];

    protected $fillable = [
        'patient_schedule_id',
        'task_key',
        'task_label',
        'sort_order',
        'outcome',
        'notes',
        'completed_at',
        'completed_by_user_id',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PatientSchedule::class, 'patient_schedule_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
