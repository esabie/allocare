<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentInvestigation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_AWAITING_EXTERNAL = 'awaiting_external';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_AWAITING_EXTERNAL,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const RIDDOR_CATEGORIES = [
        'death',
        'specified_injury',
        'over_7_day_incapacity',
        'occupational_disease',
        'dangerous_occurrence',
    ];

    protected $fillable = [
        'patient_incident_id',
        'investigation_status',
        'investigator_user_id',
        'due_at',
        'investigation_started_at',
        'investigation_completed_at',
        'investigation_summary',
        'root_cause',
        'corrective_actions',
        'riddor_reportable',
        'riddor_category',
        'riddor_reported_at',
        'riddor_reference',
        'safeguarding_concern',
        'safeguarding_referral_made',
        'safeguarding_referral_at',
        'safeguarding_authority',
        'safeguarding_reference',
    ];

    protected $casts = [
        'due_at' => 'date',
        'investigation_started_at' => 'datetime',
        'investigation_completed_at' => 'datetime',
        'riddor_reportable' => 'boolean',
        'riddor_reported_at' => 'datetime',
        'safeguarding_concern' => 'boolean',
        'safeguarding_referral_made' => 'boolean',
        'safeguarding_referral_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PatientIncident::class, 'patient_incident_id');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_user_id');
    }
}
