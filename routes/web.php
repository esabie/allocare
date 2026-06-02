<?php

use App\Http\Controllers\ProfileController;
use App\Models\PatientDocumentForm;
use App\Models\PatientCarePlanForm;
use App\Models\PatientCarePlanSummary;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientSchedule;
use App\Models\ScheduleVisitTask;
use App\Models\PatientHandover;
use App\Models\PatientWoundAssessment;
use App\Models\PrivacyRequest;
use App\Models\PatientFluidRecord;
use App\Models\PatientBowelRecord;
use App\Models\PatientVital;
use App\Models\MedicationAdministration;
use App\Models\MedicationReminder;
use App\Models\MedicationStock;
use App\Models\MedicationStockMovement;
use App\Models\DataRetentionSchedule;
use App\Models\PrivacyErasureJob;
use App\Models\PrivacyNotice;
use App\Models\PatientRiskAssessment;
use App\Models\PatientRiskAssessmentVersion;
use App\Models\IncidentInvestigation;
use App\Models\PatientIncident;
use App\Models\FormSnapshot;
use App\Models\AuditEvent;
use App\Models\CareJournalEntry;
use App\Models\StaffTrainingRecord;
use App\Models\StaffCompetency;
use App\Models\StaffSupervision;
use App\Models\StaffDocument;
use App\Models\User;
use App\Support\AuditTrail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

if (!function_exists('normalized_primary_role')) {
function normalized_primary_role($user): ?string
{
    $role = trim((string) ($user?->primary_role ?? ''));
    if ($role === '') {
        return null;
    }

    return strtolower(str_replace(' ', '_', $role));
}

function user_has_primary_role($user, array $allowedRoles): bool
{
    $normalizedRole = normalized_primary_role($user);
    if ($normalizedRole === null) {
        return false;
    }

    return in_array($normalizedRole, $allowedRoles, true);
}

function user_is_care_worker($user): bool
{
    return user_has_primary_role($user, ['care_worker']);
}

function resolve_care_worker_user_or_fail(int $userId): User
{
    $user = User::query()->findOrFail($userId);
    if (!user_is_care_worker($user)) {
        throw ValidationException::withMessages([
            'assigned_user_id' => 'Only care workers can be scheduled for patient visits.',
        ]);
    }

    return $user;
}

function normalize_employee_date_of_birth(?string $rawDateOfBirth): ?string
{
    $rawDateOfBirth = trim((string) $rawDateOfBirth);
    if ($rawDateOfBirth === '') {
        return null;
    }

    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
        try {
            $parsedDate = Carbon::createFromFormat($format, $rawDateOfBirth);
            if ($parsedDate !== false && $parsedDate->format($format) === $rawDateOfBirth) {
                if ($parsedDate->copy()->startOfDay()->gt(Carbon::today())) {
                    throw ValidationException::withMessages([
                        'date_of_birth' => 'Date of birth cannot be in the future.',
                    ]);
                }

                return $parsedDate->format('Y-m-d');
            }
        } catch (\Throwable) {
            // Try the next accepted format.
        }
    }

    throw ValidationException::withMessages([
        'date_of_birth' => 'Use DD/MM/YYYY or YYYY-MM-DD for date of birth.',
    ]);
}

function resolve_schedule_window(string $visitDate, string $startTime, string $endTime): array
{
    $startAt = Carbon::parse($visitDate.' '.$startTime);
    $endAt = Carbon::parse($visitDate.' '.$endTime);

    if ($endAt->lessThanOrEqualTo($startAt)) {
        $endAt = $endAt->copy()->addDay();
    }

    $durationMinutes = $startAt->diffInMinutes($endAt);

    if ($durationMinutes < 15) {
        throw ValidationException::withMessages([
            'end_time' => 'Shift must be at least 15 minutes long.',
        ]);
    }

    if ($durationMinutes > (24 * 60)) {
        throw ValidationException::withMessages([
            'end_time' => 'Shift cannot be longer than 24 hours.',
        ]);
    }

    return [
        'start_at' => $startAt,
        'end_at' => $endAt,
        'spans_overnight' => ! $endAt->isSameDay($startAt),
    ];
}

function resolve_patient_schedule_for_ecm(Patient $patient, ?int $scheduleId = null): ?PatientSchedule
{
    if ($scheduleId !== null) {
        return PatientSchedule::query()
            ->where('id', $scheduleId)
            ->where('patient_id', $patient->id)
            ->first();
    }

    return PatientSchedule::query()
        ->where('patient_id', $patient->id)
        ->where('end_at', '>=', now()->subDay())
        ->get()
        ->sortBy(fn (PatientSchedule $schedule) => abs($schedule->start_at?->diffInSeconds(now()) ?? PHP_INT_MAX))
        ->first();
}

function ecm_distance_metres(?float $fromLat, ?float $fromLng, ?float $toLat, ?float $toLng): ?int
{
    if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
        return null;
    }

    $earth = 6371000;
    $toRad = fn (float $v): float => deg2rad($v);
    $dLat = $toRad($toLat - $fromLat);
    $dLng = $toRad($toLng - $fromLng);
    $a = sin($dLat / 2) ** 2 + cos($toRad($fromLat)) * cos($toRad($toLat)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return (int) round($earth * $c);
}

function visit_task_catalogue(): array
{
    return [
        ['key' => 'personal_care', 'label' => 'Personal care'],
        ['key' => 'hoist_transfers', 'label' => 'Hoist transfers'],
        ['key' => 'medication_administration', 'label' => 'Medication administration'],
        ['key' => 'nutrition_hydration', 'label' => 'Nutrition & hydration'],
        ['key' => 'repositioning', 'label' => 'Repositioning'],
        ['key' => 'catheter_care', 'label' => 'Catheter care'],
        ['key' => 'peg_care', 'label' => 'PEG care'],
        ['key' => 'observations', 'label' => 'Observations'],
        ['key' => 'social_engagement', 'label' => 'Social engagement'],
        ['key' => 'wound_care', 'label' => 'Wound care'],
        ['key' => 'sleep_checks', 'label' => 'Sleep checks'],
    ];
}

function seed_schedule_visit_tasks(PatientSchedule $schedule): void
{
    foreach (visit_task_catalogue() as $index => $task) {
        ScheduleVisitTask::query()->firstOrCreate(
            [
                'patient_schedule_id' => $schedule->id,
                'task_key' => $task['key'],
            ],
            [
                'task_label' => $task['label'],
                'sort_order' => $index,
            ]
        );
    }
}

function map_schedule_visit_task(ScheduleVisitTask $task): array
{
    return [
        'id' => $task->id,
        'taskKey' => $task->task_key,
        'taskLabel' => $task->task_label,
        'outcome' => $task->outcome,
        'notes' => $task->notes,
        'completedAt' => $task->completed_at?->toIso8601String(),
        'completedBy' => $task->completedBy
            ? ['id' => $task->completedBy->id, 'name' => format_care_journal_author_name($task->completedBy)]
            : null,
    ];
}

function load_schedule_visit_tasks(PatientSchedule $schedule): array
{
    seed_schedule_visit_tasks($schedule);

    return $schedule->visitTasks()
        ->with('completedBy:id,name,first_name,surname')
        ->orderBy('sort_order')
        ->get()
        ->map(fn (ScheduleVisitTask $task) => map_schedule_visit_task($task))
        ->values()
        ->all();
}

function build_patient_observation_chart_series(Patient $patient, int $days = 30): array
{
    $from = now()->subDays($days)->startOfDay();
    $vitals = PatientVital::query()
        ->where('patient_id', $patient->id)
        ->where('recorded_at', '>=', $from)
        ->orderBy('recorded_at')
        ->orderBy('id')
        ->get();

    $makeSeries = function (string $field) use ($vitals): array {
        return $vitals
            ->filter(fn (PatientVital $vital) => $vital->{$field} !== null)
            ->map(function (PatientVital $vital) use ($field) {
                $value = $vital->{$field};
                if (in_array($field, ['temperature_celsius', 'blood_glucose_mmol', 'weight_kg'], true)) {
                    $value = (float) $value;
                } else {
                    $value = (int) $value;
                }

                return [
                    'at' => $vital->recorded_at?->toIso8601String(),
                    'label' => $vital->recorded_at?->format('d M H:i') ?? '',
                    'value' => $value,
                ];
            })
            ->values()
            ->all();
    };

    return [
        'from' => $from->toDateString(),
        'to' => now()->toDateString(),
        'series' => [
            'heart_rate' => $makeSeries('heart_rate'),
            'bp_systolic' => $makeSeries('bp_systolic'),
            'spo2' => $makeSeries('spo2'),
            'temperature_celsius' => $makeSeries('temperature_celsius'),
            'blood_glucose_mmol' => $makeSeries('blood_glucose_mmol'),
            'pain_score' => $makeSeries('pain_score'),
        ],
        'thresholds' => [
            'heart_rate' => ['low' => 50, 'high' => 100],
            'bp_systolic' => ['low' => 90, 'high' => 180],
            'spo2' => ['low' => 94, 'critical' => 90],
            'temperature' => ['low' => 35.0, 'high' => 38.0],
            'glucose' => ['low' => 4.0, 'high' => 11.0],
            'pain' => ['high' => 7],
        ],
    ];
}

function map_patient_handover(PatientHandover $handover): array
{
    $author = $handover->author;
    $authorName = $author
        ? trim((string) ($author->name ?: (($author->first_name ?? '').' '.($author->surname ?? ''))))
        : '';

    return [
        'id' => $handover->id,
        'shiftType' => $handover->shift_type,
        'shiftDate' => $handover->shift_date?->toDateString(),
        'shiftDateLabel' => $handover->shift_date?->format('d M Y'),
        'recordedAt' => $handover->recorded_at?->toIso8601String(),
        'recordedAtLabel' => $handover->recorded_at?->format('d M Y, H:i'),
        'scheduleId' => $handover->patient_schedule_id,
        'day' => [
            'presentation' => $handover->presentation,
            'careDelivered' => $handover->care_delivered,
            'medicationSummary' => $handover->medication_summary,
            'risksChanges' => $handover->risks_changes,
            'handoverNotes' => $handover->handover_notes,
        ],
        'night' => [
            'sleepSummary' => $handover->sleep_summary,
            'disturbances' => $handover->disturbances,
            'nightMedications' => $handover->night_medications,
            'seizureRespiratoryEvents' => $handover->seizure_respiratory_events,
            'morningPriorities' => $handover->morning_priorities,
        ],
        'author' => [
            'id' => $author?->id,
            'name' => $authorName !== '' ? $authorName : 'Unknown staff',
        ],
    ];
}

function validate_patient_handover_payload(array $payload): array
{
    $validated = validator($payload, [
        'shift_type' => ['required', 'string', 'in:day,night'],
        'shift_date' => ['required', 'date'],
        'schedule_id' => ['nullable', 'integer'],
        'presentation' => ['nullable', 'string', 'max:5000'],
        'care_delivered' => ['nullable', 'string', 'max:5000'],
        'medication_summary' => ['nullable', 'string', 'max:5000'],
        'risks_changes' => ['nullable', 'string', 'max:5000'],
        'handover_notes' => ['nullable', 'string', 'max:5000'],
        'sleep_summary' => ['nullable', 'string', 'max:5000'],
        'disturbances' => ['nullable', 'string', 'max:5000'],
        'night_medications' => ['nullable', 'string', 'max:5000'],
        'seizure_respiratory_events' => ['nullable', 'string', 'max:5000'],
        'morning_priorities' => ['nullable', 'string', 'max:5000'],
    ])->validate();

    $trim = fn (?string $value): ?string => ($value = trim((string) $value)) !== '' ? $value : null;

    $validated['presentation'] = $trim($validated['presentation'] ?? null);
    $validated['care_delivered'] = $trim($validated['care_delivered'] ?? null);
    $validated['medication_summary'] = $trim($validated['medication_summary'] ?? null);
    $validated['risks_changes'] = $trim($validated['risks_changes'] ?? null);
    $validated['handover_notes'] = $trim($validated['handover_notes'] ?? null);
    $validated['sleep_summary'] = $trim($validated['sleep_summary'] ?? null);
    $validated['disturbances'] = $trim($validated['disturbances'] ?? null);
    $validated['night_medications'] = $trim($validated['night_medications'] ?? null);
    $validated['seizure_respiratory_events'] = $trim($validated['seizure_respiratory_events'] ?? null);
    $validated['morning_priorities'] = $trim($validated['morning_priorities'] ?? null);

    if ($validated['shift_type'] === 'day') {
        $hasContent = collect([
            $validated['presentation'],
            $validated['care_delivered'],
            $validated['medication_summary'],
            $validated['risks_changes'],
            $validated['handover_notes'],
        ])->filter()->isNotEmpty();

        if (!$hasContent) {
            throw ValidationException::withMessages([
                'presentation' => 'Enter at least one day handover field.',
            ]);
        }
    } else {
        $hasContent = collect([
            $validated['sleep_summary'],
            $validated['disturbances'],
            $validated['night_medications'],
            $validated['seizure_respiratory_events'],
            $validated['morning_priorities'],
        ])->filter()->isNotEmpty();

        if (!$hasContent) {
            throw ValidationException::withMessages([
                'sleep_summary' => 'Enter at least one night handover field.',
            ]);
        }
    }

    return $validated;
}

function evaluate_wound_assessment_alerts(PatientWoundAssessment $assessment): array
{
    $alerts = [];

    if ($assessment->escalation_required) {
        $alerts[] = 'Wound escalation flagged — review required';
    }

    if (trim((string) $assessment->infection_signs) !== '') {
        $alerts[] = 'Infection signs recorded: '.Str::limit(trim((string) $assessment->infection_signs), 80);
    }

    if ($assessment->pain_score !== null && $assessment->pain_score >= 7) {
        $alerts[] = 'High wound pain score: '.$assessment->pain_score.'/10';
    }

    if (in_array($assessment->pressure_ulcer_grade, ['category_3', 'category_4', 'unstageable'], true)) {
        $alerts[] = 'High-grade pressure injury: '.str_replace('_', ' ', (string) $assessment->pressure_ulcer_grade);
    }

    return $alerts;
}

function map_patient_wound_assessment(PatientWoundAssessment $assessment): array
{
    $recorder = $assessment->recordedBy;
    $authorName = $recorder
        ? trim((string) ($recorder->name ?: (($recorder->first_name ?? '').' '.($recorder->surname ?? ''))))
        : '';

    $area = null;
    if ($assessment->length_cm !== null && $assessment->width_cm !== null) {
        $area = round((float) $assessment->length_cm * (float) $assessment->width_cm, 1);
    }

    return [
        'id' => $assessment->id,
        'woundSite' => $assessment->wound_site,
        'woundType' => $assessment->wound_type,
        'pressureUlcerGrade' => $assessment->pressure_ulcer_grade,
        'pressureUlcerGradeLabel' => $assessment->pressure_ulcer_grade
            ? Str::of($assessment->pressure_ulcer_grade)->replace('_', ' ')->title()->toString()
            : null,
        'lengthCm' => $assessment->length_cm,
        'widthCm' => $assessment->width_cm,
        'depthCm' => $assessment->depth_cm,
        'areaCm2' => $area,
        'exudate' => $assessment->exudate,
        'periwoundCondition' => $assessment->periwound_condition,
        'painScore' => $assessment->pain_score,
        'dressingType' => $assessment->dressing_type,
        'pressureRegime' => $assessment->pressure_regime,
        'infectionSigns' => $assessment->infection_signs,
        'escalationRequired' => $assessment->escalation_required,
        'bodyMapNotes' => $assessment->body_map_notes,
        'bodyMapRegion' => $assessment->body_map_region,
        'bodyMapRegionLabel' => $assessment->body_map_region
            ? Str::of($assessment->body_map_region)->replace('_', ' ')->title()->toString()
            : null,
        'photoUrl' => $assessment->photo_path
            ? Storage::disk('public')->url($assessment->photo_path)
            : null,
        'reviewDueAt' => $assessment->review_due_at?->toDateString(),
        'reviewDueAtLabel' => $assessment->review_due_at?->format('d M Y'),
        'reviewOverdue' => $assessment->review_due_at
            && $assessment->review_due_at->isPast(),
        'planActions' => $assessment->plan_actions,
        'thresholdAlerts' => evaluate_wound_assessment_alerts($assessment),
        'recordedAt' => $assessment->recorded_at?->toIso8601String(),
        'recordedAtLabel' => $assessment->recorded_at?->format('d M Y, H:i'),
        'recordedBy' => [
            'id' => $recorder?->id,
            'name' => $authorName !== '' ? $authorName : 'Unknown staff',
        ],
    ];
}

function build_patient_wound_measurement_chart_series(Patient $patient, int $days = 30): array
{
    $from = now()->subDays($days)->startOfDay();
    $assessments = PatientWoundAssessment::query()
        ->where('patient_id', $patient->id)
        ->where('recorded_at', '>=', $from)
        ->orderBy('recorded_at')
        ->orderBy('id')
        ->get();

    $makeSeries = function (string $field) use ($assessments): array {
        return $assessments
            ->filter(fn (PatientWoundAssessment $row) => $row->{$field} !== null)
            ->map(fn (PatientWoundAssessment $row) => [
                'at' => $row->recorded_at?->toIso8601String(),
                'label' => $row->recorded_at?->format('d M H:i') ?? '',
                'value' => (float) $row->{$field},
            ])
            ->values()
            ->all();
    };

    return [
        'from' => $from->toDateString(),
        'to' => now()->toDateString(),
        'series' => [
            'length_cm' => $makeSeries('length_cm'),
            'width_cm' => $makeSeries('width_cm'),
            'area_cm2' => $assessments
                ->filter(fn (PatientWoundAssessment $row) => $row->length_cm !== null && $row->width_cm !== null)
                ->map(fn (PatientWoundAssessment $row) => [
                    'at' => $row->recorded_at?->toIso8601String(),
                    'label' => $row->recorded_at?->format('d M H:i') ?? '',
                    'value' => round((float) $row->length_cm * (float) $row->width_cm, 1),
                ])
                ->values()
                ->all(),
        ],
    ];
}

function append_wound_escalation_care_alerts($careAlerts, int $limit = 3): void
{
    if (!Schema::hasTable('patient_wound_assessments')) {
        return;
    }

    $added = 0;
    $recent = PatientWoundAssessment::query()
        ->where('recorded_at', '>=', now()->subDays(7))
        ->with('patient:id,name,url_key')
        ->orderByDesc('recorded_at')
        ->limit(20)
        ->get();

    foreach ($recent as $assessment) {
        $alerts = evaluate_wound_assessment_alerts($assessment);
        if (empty($alerts)) {
            continue;
        }

        $patientSlug = $assessment->patient?->url_key;
        $isCritical = $assessment->escalation_required
            || in_array($assessment->pressure_ulcer_grade, ['category_3', 'category_4', 'unstageable'], true);

        $careAlerts->push([
            'label' => 'WOUND ALERT',
            'patient' => $assessment->patient?->name ?? 'Unknown',
            'details' => implode('; ', array_slice($alerts, 0, 2)),
            'action' => 'Review',
            'accent' => $isCritical ? 'border-red-400' : 'border-amber-400',
            'panel' => $isCritical ? 'bg-red-50' : 'bg-amber-50',
            'time' => $assessment->recorded_at,
            'patientUrlKey' => $patientSlug,
            'href' => $patientSlug ? route('patients.wound-care', $patientSlug) : null,
        ]);

        $added++;
        if ($added >= $limit) {
            break;
        }
    }
}

function map_patient_fluid_record(PatientFluidRecord $record): array
{
    $recorder = $record->recordedBy;
    $authorName = $recorder
        ? trim((string) ($recorder->name ?: (($recorder->first_name ?? '').' '.($recorder->surname ?? ''))))
        : '';

    return [
        'id' => $record->id,
        'fluidIntakeMl' => $record->fluid_intake_ml,
        'fluidOutputMl' => $record->fluid_output_ml,
        'fluidType' => $record->fluid_type,
        'notes' => $record->notes,
        'recordedAt' => $record->recorded_at?->toIso8601String(),
        'recordedAtLabel' => $record->recorded_at?->format('d M Y, H:i'),
        'recordedBy' => [
            'id' => $recorder?->id,
            'name' => $authorName !== '' ? $authorName : 'Unknown staff',
        ],
    ];
}

function map_patient_bowel_record(PatientBowelRecord $record): array
{
    $recorder = $record->recordedBy;
    $authorName = $recorder
        ? trim((string) ($recorder->name ?: (($recorder->first_name ?? '').' '.($recorder->surname ?? ''))))
        : '';

    return [
        'id' => $record->id,
        'bowelOpened' => $record->bowel_opened,
        'bristolType' => $record->bristol_type,
        'bristolLabel' => $record->bristol_type
            ? (PatientBowelRecord::BRISTOL_LABELS[$record->bristol_type] ?? 'Type '.$record->bristol_type)
            : null,
        'continenceStatus' => $record->continence_status,
        'notes' => $record->notes,
        'recordedAt' => $record->recorded_at?->toIso8601String(),
        'recordedAtLabel' => $record->recorded_at?->format('d M Y, H:i'),
        'recordedBy' => [
            'id' => $recorder?->id,
            'name' => $authorName !== '' ? $authorName : 'Unknown staff',
        ],
    ];
}

function build_patient_fluid_balance_summary(Patient $patient, int $days = 7): array
{
    $from = now()->subDays($days - 1)->startOfDay();
    $records = PatientFluidRecord::query()
        ->where('patient_id', $patient->id)
        ->where('recorded_at', '>=', $from)
        ->orderBy('recorded_at')
        ->get();

    $byDay = [];
    foreach ($records as $record) {
        $key = $record->recorded_at->toDateString();
        if (!isset($byDay[$key])) {
            $byDay[$key] = ['date' => $key, 'intakeMl' => 0, 'outputMl' => 0];
        }
        $byDay[$key]['intakeMl'] += (int) ($record->fluid_intake_ml ?? 0);
        $byDay[$key]['outputMl'] += (int) ($record->fluid_output_ml ?? 0);
    }

    return array_values($byDay);
}

function evaluate_fluid_balance_alerts(Patient $patient): array
{
    $alerts = [];
    $todayIntake = (int) PatientFluidRecord::query()
        ->where('patient_id', $patient->id)
        ->whereDate('recorded_at', now()->toDateString())
        ->sum('fluid_intake_ml');

    if ($todayIntake > 0 && $todayIntake < 800 && now()->hour >= 14) {
        $alerts[] = 'Low fluid intake today: '.$todayIntake.' ml recorded (review hydration plan).';
    }

    return $alerts;
}

function privacy_request_type_label(string $type): string
{
    return match ($type) {
        PrivacyRequest::TYPE_ERASURE => 'Right to erasure',
        PrivacyRequest::TYPE_DATA_BREACH => 'Personal data breach',
        default => 'Subject access request',
    };
}

function map_privacy_request(PrivacyRequest $request): array
{
    $requester = $request->requestedBy;
    $handler = $request->handledBy;
    $authorName = fn (?User $user) => $user
        ? trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))))
        : '';

    $icoReviewDue = $request->request_type === PrivacyRequest::TYPE_DATA_BREACH
        && $request->discovered_at
        && $request->ico_notification_required
        && $request->ico_notified_at === null;

    $icoDeadline = $request->discovered_at?->copy()->addHours(72);

    return [
        'id' => $request->id,
        'requestType' => $request->request_type,
        'requestTypeLabel' => privacy_request_type_label($request->request_type),
        'status' => $request->status,
        'patientId' => $request->patient_id,
        'patientName' => $request->patient?->name,
        'patientUrlKey' => $request->patient?->url_key,
        'subjectName' => $request->subject_name,
        'subjectEmail' => $request->subject_email,
        'requestDetails' => $request->request_details,
        'outcomeNotes' => $request->outcome_notes,
        'dueAt' => $request->due_at?->toDateString(),
        'dueAtLabel' => $request->due_at?->format('d M Y'),
        'discoveredAt' => $request->discovered_at?->toIso8601String(),
        'discoveredAtLabel' => $request->discovered_at?->format('d M Y, H:i'),
        'icoNotificationRequired' => $request->ico_notification_required,
        'icoNotifiedAt' => $request->ico_notified_at?->toIso8601String(),
        'icoNotifiedAtLabel' => $request->ico_notified_at?->format('d M Y, H:i'),
        'icoDeadlineLabel' => $icoDeadline?->format('d M Y, H:i'),
        'icoReviewOverdue' => $icoReviewDue
            && $icoDeadline
            && $icoDeadline->isPast()
            && !in_array($request->status, ['completed', 'rejected'], true),
        'individualsAffectedCount' => $request->individuals_affected_count,
        'breachCategories' => $request->breach_categories,
        'completedAt' => $request->completed_at?->toIso8601String(),
        'completedAtLabel' => $request->completed_at?->format('d M Y, H:i'),
        'isOverdue' => $request->due_at
            && !in_array($request->status, ['completed', 'rejected'], true)
            && $request->due_at->isPast(),
        'requestedBy' => [
            'id' => $requester?->id,
            'name' => ($name = $authorName($requester)) !== '' ? $name : 'Unknown',
        ],
        'handledBy' => $handler
            ? ['id' => $handler->id, 'name' => ($name = $authorName($handler)) !== '' ? $name : 'Unknown']
            : null,
        'createdAtLabel' => $request->created_at?->format('d M Y, H:i'),
        'erasureJob' => map_privacy_erasure_job($request->erasureJob),
    ];
}

function prepare_sar_pdf_view_data(Patient $patient, PrivacyRequest $privacyRequest): array
{
    $export = build_subject_access_export($patient);
    $profile = $export['patient']['profile'] ?? [];

    $profileRows = [
        'Name' => $profile['name'] ?? $patient->name,
        'Preferred name' => $profile['preferredName'] ?? '',
        'NHS number' => $profile['nhsNumber'] ?? $patient->nhs_number,
        'Date of birth' => $profile['dob'] ?? $patient->dob,
        'Address' => $profile['address'] ?? $patient->address,
        'GP' => trim(($profile['gpName'] ?? '').' '.($profile['gpPractice'] ?? '')),
        'Primary diagnosis' => $profile['primaryDiagnosis'] ?? '',
        'RAG status' => $profile['ragStatus'] ?? '',
    ];

    $observations = collect($export['observations'] ?? [])->take(25)->map(fn ($row) => [
        'when' => $row['recordedAtLabel'] ?? '',
        'hr' => $row['heartRate'] ?? null,
        'bp' => isset($row['bpSystolic']) ? $row['bpSystolic'].'/'.($row['bpDiastolic'] ?? '—') : null,
        'spo2' => $row['spo2'] ?? null,
        'notes' => $row['otherObservation'] ?? '',
    ])->all();

    $fluidRecords = collect($export['fluid_records'] ?? [])->take(20)->map(fn ($row) => [
        'when' => $row['recordedAtLabel'] ?? '',
        'intake' => $row['fluidIntakeMl'] ?? null,
        'output' => $row['fluidOutputMl'] ?? null,
        'notes' => $row['notes'] ?? '',
    ])->all();

    $bowelRecords = collect($export['bowel_records'] ?? [])->take(20)->map(fn ($row) => [
        'when' => $row['recordedAtLabel'] ?? '',
        'summary' => trim(
            ($row['bowelOpened'] ? 'Opened' : 'Not opened').' '.
            ($row['bristolLabel'] ?? '').' '.
            ($row['continenceStatus'] ?? '')
        ),
        'notes' => $row['notes'] ?? '',
    ])->all();

    $journal = collect($export['care_journal'] ?? [])->take(15)->map(fn ($row) => [
        'when' => $row['recordedAtLabel'] ?? '',
        'author' => $row['author']['name'] ?? 'Unknown',
        'body' => Str::limit((string) ($row['body'] ?? ''), 500),
    ])->all();

    return [
        'patientName' => $patient->name,
        'patientReference' => $patient->reference,
        'nhsNumber' => $patient->nhs_number,
        'exportedAt' => now()->format('d M Y H:i'),
        'requestId' => $privacyRequest->id,
        'profileRows' => $profileRows,
        'observations' => $observations,
        'fluidRecords' => $fluidRecords,
        'bowelRecords' => $bowelRecords,
        'medications' => $export['medications'] ?? [],
        'journal' => $journal,
    ];
}

function append_privacy_breach_care_alerts($careAlerts, int $limit = 2): void
{
    if (!Schema::hasTable('privacy_requests')) {
        return;
    }

    $added = 0;
    $breaches = PrivacyRequest::query()
        ->where('request_type', PrivacyRequest::TYPE_DATA_BREACH)
        ->whereNotIn('status', ['completed', 'rejected'])
        ->with('patient:id,name,url_key')
        ->orderByDesc('created_at')
        ->limit(10)
        ->get();

    foreach ($breaches as $breach) {
        $icoDeadline = $breach->discovered_at?->copy()->addHours(72);
        $needsIco = $breach->ico_notification_required && $breach->ico_notified_at === null;
        $overdue = $needsIco && $icoDeadline && $icoDeadline->isPast();

        $careAlerts->push([
            'label' => 'DATA BREACH',
            'patient' => $breach->subject_name ?? 'Unknown',
            'details' => $overdue
                ? 'ICO notification window may be overdue — review breach #'.$breach->id
                : 'Open breach record — '.$breach->breach_categories,
            'action' => 'Review',
            'accent' => $overdue ? 'border-red-400' : 'border-rose-400',
            'panel' => $overdue ? 'bg-red-50' : 'bg-rose-50',
            'time' => $breach->discovered_at ?? $breach->created_at,
            'patientUrlKey' => $breach->patient?->url_key,
            'privacyRequestId' => $breach->id,
            'href' => route('reports.gdpr').'#privacy-request-'.$breach->id,
        ]);

        $added++;
        if ($added >= $limit) {
            break;
        }
    }
}

function append_wound_review_care_alerts($careAlerts, int $limit = 2): void
{
    if (!Schema::hasTable('patient_wound_assessments')) {
        return;
    }

    $added = 0;
    $due = PatientWoundAssessment::query()
        ->whereNotNull('review_due_at')
        ->whereDate('review_due_at', '<=', now()->toDateString())
        ->with('patient:id,name,url_key')
        ->orderBy('review_due_at')
        ->limit(10)
        ->get();

    foreach ($due as $assessment) {
        $patientSlug = $assessment->patient?->url_key;
        $careAlerts->push([
            'label' => 'WOUND REVIEW',
            'patient' => $assessment->patient?->name ?? 'Unknown',
            'details' => 'Wound review due '.$assessment->review_due_at->format('d M Y').' — '.$assessment->wound_site,
            'action' => 'Reassess',
            'accent' => 'border-amber-400',
            'panel' => 'bg-amber-50',
            'time' => $assessment->review_due_at,
            'patientUrlKey' => $patientSlug,
            'href' => $patientSlug ? route('patients.wound-care', $patientSlug) : null,
        ]);

        $added++;
        if ($added >= $limit) {
            break;
        }
    }
}

function risk_assessment_templates(): array
{
    return [
        ['slug' => 'falls-risk', 'title' => 'Falls Risk', 'suggestedControls' => ['2:1 support', 'Transfer belt', 'Falls mat']],
        ['slug' => 'skin-integrity', 'title' => 'Skin Integrity Risk', 'suggestedControls' => ['Repositioning', 'Pressure mattress', 'Skin checks']],
        ['slug' => 'aspiration-risk', 'title' => 'Aspiration / Choking Risk', 'suggestedControls' => ['IDDSI Level 4', 'Supervised feeding', 'Thickened fluids']],
        ['slug' => 'medication-risk', 'title' => 'Medication Administration Risk', 'suggestedControls' => ['Double-check eMAR', 'Time-critical flag', 'Pharmacy review']],
        ['slug' => 'elopement-risk', 'title' => 'Absconding / Missing Person Risk', 'suggestedControls' => ['Door sensor', 'Escort plan', 'Welfare check']],
        ['slug' => 'infection-risk', 'title' => 'Infection Prevention Risk', 'suggestedControls' => ['PPE protocol', 'Daily observations', 'Isolation plan']],
    ];
}

function risk_assessment_template(string $slug): ?array
{
    foreach (risk_assessment_templates() as $template) {
        if ($template['slug'] === $slug) {
            return $template;
        }
    }

    return null;
}

function map_patient_risk_assessment(?PatientRiskAssessment $assessment, array $template, ?string $authorName = null): array
{
    $level = $assessment?->risk_level;
    $levelLabel = $level ? Str::of($level)->replace('_', ' ')->title()->toString() : null;

    return [
        'slug' => $template['slug'],
        'title' => $template['title'],
        'suggestedControls' => $template['suggestedControls'],
        'riskLevel' => $level,
        'riskLevelLabel' => $levelLabel,
        'status' => $assessment?->status ?? 'draft',
        'triggers' => $assessment?->triggers,
        'currentControls' => $assessment?->current_controls,
        'mitigationPlan' => $assessment?->mitigation_plan,
        'ownerName' => $assessment?->owner_name,
        'lastReviewedAt' => $assessment?->last_reviewed_at?->toDateString(),
        'lastReviewedAtLabel' => $assessment?->last_reviewed_at?->format('d M Y'),
        'nextReviewDueAt' => $assessment?->next_review_due_at?->toDateString(),
        'nextReviewDueAtLabel' => $assessment?->next_review_due_at?->format('d M Y'),
        'reviewCycleMonths' => $assessment?->review_cycle_months ?? 3,
        'reviewOverdue' => $assessment?->status === 'active'
            && $assessment->next_review_due_at
            && $assessment->next_review_due_at->isPast(),
        'updatedAtLabel' => $assessment?->updated_at?->format('d M Y, H:i'),
        'authorName' => $authorName,
        'hasRecord' => $assessment !== null,
        'assessmentId' => $assessment?->id,
    ];
}

function risk_assessment_snapshot_from_model(PatientRiskAssessment $assessment): array
{
    return [
        'risk_level' => $assessment->risk_level,
        'status' => $assessment->status,
        'triggers' => $assessment->triggers,
        'current_controls' => $assessment->current_controls,
        'mitigation_plan' => $assessment->mitigation_plan,
        'owner_name' => $assessment->owner_name,
        'last_reviewed_at' => $assessment->last_reviewed_at?->toDateString(),
        'next_review_due_at' => $assessment->next_review_due_at?->toDateString(),
        'review_cycle_months' => $assessment->review_cycle_months,
    ];
}

function risk_assessment_change_summary(?array $previous, array $current): string
{
    if ($previous === null) {
        return 'Initial assessment recorded';
    }

    $labels = [
        'risk_level' => 'Risk level',
        'status' => 'Status',
        'triggers' => 'Triggers',
        'current_controls' => 'Controls',
        'mitigation_plan' => 'Mitigation plan',
        'owner_name' => 'Owner',
        'last_reviewed_at' => 'Last reviewed',
        'next_review_due_at' => 'Next review',
        'review_cycle_months' => 'Review cycle',
    ];

    $parts = [];
    foreach ($labels as $key => $label) {
        $old = $previous[$key] ?? null;
        $new = $current[$key] ?? null;
        if ((string) $old !== (string) $new) {
            $parts[] = $label.': '.($old ?: '—').' → '.($new ?: '—');
        }
    }

    return $parts !== [] ? implode('; ', array_slice($parts, 0, 4)) : 'Assessment updated';
}

function record_risk_assessment_version_if_changed(PatientRiskAssessment $assessment, ?User $user): void
{
    if (!Schema::hasTable('patient_risk_assessment_versions')) {
        return;
    }

    $snapshot = risk_assessment_snapshot_from_model($assessment);
    $latest = PatientRiskAssessmentVersion::query()
        ->where('patient_risk_assessment_id', $assessment->id)
        ->orderByDesc('recorded_at')
        ->first();

    $previous = $latest?->snapshot;
    if ($previous !== null && $previous === $snapshot) {
        return;
    }

    PatientRiskAssessmentVersion::query()->create([
        'patient_risk_assessment_id' => $assessment->id,
        'patient_id' => $assessment->patient_id,
        'risk_slug' => $assessment->risk_slug,
        'snapshot' => $snapshot,
        'change_summary' => risk_assessment_change_summary($previous, $snapshot),
        'recorded_by_user_id' => $user?->id,
        'recorded_at' => now(),
    ]);
}

function user_display_name(?User $user): ?string
{
    if (!$user) {
        return null;
    }

    $name = trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))));

    return $name !== '' ? $name : 'Unknown user';
}

function list_risk_assessment_versions(PatientRiskAssessment $assessment, int $limit = 20): array
{
    if (!Schema::hasTable('patient_risk_assessment_versions')) {
        return [];
    }

    $userIds = PatientRiskAssessmentVersion::query()
        ->where('patient_risk_assessment_id', $assessment->id)
        ->pluck('recorded_by_user_id')
        ->filter()
        ->unique();

    $namesById = User::query()
        ->whereIn('id', $userIds)
        ->get(['id', 'name', 'first_name', 'surname'])
        ->mapWithKeys(fn (User $user) => [$user->id => user_display_name($user)]);

    return PatientRiskAssessmentVersion::query()
        ->where('patient_risk_assessment_id', $assessment->id)
        ->orderByDesc('recorded_at')
        ->limit($limit)
        ->get()
        ->map(function (PatientRiskAssessmentVersion $version) use ($namesById) {
            $snapshot = $version->snapshot ?? [];
            $level = $snapshot['risk_level'] ?? null;

            return [
                'id' => $version->id,
                'recordedAt' => $version->recorded_at?->toIso8601String(),
                'recordedAtLabel' => $version->recorded_at?->format('d M Y, H:i'),
                'authorName' => $namesById[$version->recorded_by_user_id] ?? 'Unknown user',
                'changeSummary' => $version->change_summary,
                'riskLevel' => $level,
                'riskLevelLabel' => $level ? Str::of($level)->title()->toString() : null,
                'status' => $snapshot['status'] ?? null,
                'statusLabel' => isset($snapshot['status'])
                    ? Str::of($snapshot['status'])->replace('_', ' ')->title()->toString()
                    : null,
                'snapshot' => $snapshot,
            ];
        })
        ->values()
        ->all();
}

function build_risk_assessment_pdf_payload(Patient $patient, PatientRiskAssessment $assessment, array $template, ?string $authorName): array
{
    $level = $assessment->risk_level;

    return [
        'risk_level' => $level,
        'risk_level_label' => $level ? Str::of($level)->title()->toString() : '—',
        'status_label' => Str::of($assessment->status)->replace('_', ' ')->title()->toString(),
        'owner_name' => $assessment->owner_name,
        'last_reviewed_at_label' => $assessment->last_reviewed_at?->format('d M Y'),
        'next_review_due_at_label' => $assessment->next_review_due_at?->format('d M Y'),
        'review_cycle_months' => $assessment->review_cycle_months,
        'triggers' => $assessment->triggers,
        'current_controls' => $assessment->current_controls,
        'mitigation_plan' => $assessment->mitigation_plan,
        'author_name' => $authorName,
        'updated_at_label' => $assessment->updated_at?->format('d M Y, H:i'),
    ];
}

function append_risk_review_care_alerts($careAlerts, int $limit = 2): void
{
    if (!Schema::hasTable('patient_risk_assessments')) {
        return;
    }

    $added = 0;
    $due = PatientRiskAssessment::query()
        ->where('status', 'active')
        ->whereNotNull('next_review_due_at')
        ->whereDate('next_review_due_at', '<=', now()->toDateString())
        ->with('patient:id,name,url_key')
        ->orderBy('next_review_due_at')
        ->limit(10)
        ->get();

    foreach ($due as $assessment) {
        $template = risk_assessment_template($assessment->risk_slug);
        $title = $template['title'] ?? Str::of($assessment->risk_slug)->replace('-', ' ')->title()->toString();
        $patientSlug = $assessment->patient?->url_key;

        $careAlerts->push([
            'label' => 'RISK REVIEW',
            'patient' => $assessment->patient?->name ?? 'Unknown',
            'details' => $title.' review overdue (due '.$assessment->next_review_due_at->format('d M Y').')',
            'action' => 'Review',
            'accent' => 'border-orange-400',
            'panel' => 'bg-orange-50',
            'time' => $assessment->next_review_due_at,
            'patientUrlKey' => $patientSlug,
            'riskSlug' => $assessment->risk_slug,
            'href' => $patientSlug
                ? route('patients.risks.show', ['patient' => $patientSlug, 'risk' => $assessment->risk_slug])
                : null,
        ]);

        $added++;
        if ($added >= $limit) {
            break;
        }
    }
}

function list_mar_witness_staff(): array
{
    return User::query()
        ->whereIn('primary_role', ['care_worker', 'supervisor', 'care_manager', 'admin', 'super_admin'])
        ->orderBy('name')
        ->get(['id', 'name', 'first_name', 'surname'])
        ->map(function (User $user) {
            $name = trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))));

            return ['id' => $user->id, 'name' => $name !== '' ? $name : 'Staff #'.$user->id];
        })
        ->values()
        ->all();
}

function map_medication_stock(?MedicationStock $stock, PatientMedication $medication): array
{
    return [
        'medicationId' => $medication->id,
        'medicationName' => $medication->name,
        'isControlled' => $medication->is_controlled,
        'balance' => $stock ? (float) $stock->balance : 0,
        'unit' => $stock?->unit ?? 'doses',
        'reconciledAtLabel' => $stock?->reconciled_at?->format('d M Y, H:i'),
        'lowStock' => $medication->is_controlled && $stock && (float) $stock->balance <= 5,
    ];
}

function record_medication_stock_movement(
    PatientMedication $medication,
    string $movementType,
    float $quantityDelta,
    ?User $user = null,
    ?string $notes = null,
    ?int $administrationId = null,
): MedicationStock {
    $stock = MedicationStock::query()->firstOrCreate(
        ['patient_medication_id' => $medication->id],
        ['balance' => 0, 'unit' => 'doses'],
    );

    $newBalance = max(0, round((float) $stock->balance + $quantityDelta, 2));

    MedicationStockMovement::query()->create([
        'patient_medication_id' => $medication->id,
        'recorded_by_user_id' => $user?->id,
        'movement_type' => $movementType,
        'quantity_delta' => $quantityDelta,
        'balance_after' => $newBalance,
        'notes' => $notes,
        'medication_administration_id' => $administrationId,
    ]);

    $stock->update(['balance' => $newBalance]);

    return $stock->fresh();
}

function map_data_retention_schedule(DataRetentionSchedule $row): array
{
    $reviewDue = $row->last_reviewed_at
        ? $row->last_reviewed_at->copy()->addMonths((int) $row->review_cycle_months)
        : null;

    return [
        'id' => $row->id,
        'dataCategory' => $row->data_category,
        'retentionPeriod' => $row->retention_period,
        'legalBasis' => $row->legal_basis,
        'reviewCycleMonths' => $row->review_cycle_months,
        'lastReviewedAt' => $row->last_reviewed_at?->toDateString(),
        'lastReviewedAtLabel' => $row->last_reviewed_at?->format('d M Y'),
        'reviewDueAt' => $reviewDue?->toDateString(),
        'reviewOverdue' => $reviewDue && $reviewDue->isPast(),
        'notes' => $row->notes,
    ];
}

function map_privacy_notice(PrivacyNotice $notice): array
{
    return [
        'id' => $notice->id,
        'title' => $notice->title,
        'version' => $notice->version,
        'summary' => $notice->summary,
        'content' => $notice->content,
        'publishedAt' => $notice->published_at?->toDateString(),
        'publishedAtLabel' => $notice->published_at?->format('d M Y'),
        'isActive' => $notice->is_active,
    ];
}

function map_privacy_erasure_job(?PrivacyErasureJob $job): ?array
{
    if (!$job) {
        return null;
    }

    return [
        'id' => $job->id,
        'status' => $job->status,
        'statusLabel' => Str::of($job->status)->replace('_', ' ')->title()->toString(),
        'scheduledAtLabel' => $job->scheduled_at?->format('d M Y, H:i'),
        'processedAtLabel' => $job->processed_at?->format('d M Y, H:i'),
        'resultSummary' => $job->result_summary,
        'errorMessage' => $job->error_message,
    ];
}

function append_data_retention_care_alerts($careAlerts, int $limit = 2): void
{
    if (!Schema::hasTable('data_retention_schedules')) {
        return;
    }

    $added = 0;
    $schedules = DataRetentionSchedule::query()->orderBy('data_category')->limit(20)->get();

    foreach ($schedules as $schedule) {
        $mapped = map_data_retention_schedule($schedule);
        if (!($mapped['reviewOverdue'] ?? false)) {
            continue;
        }

        $careAlerts->push([
            'label' => 'RETENTION REVIEW',
            'patient' => $schedule->data_category,
            'details' => 'Data retention schedule review overdue',
            'action' => 'Review',
            'accent' => 'border-violet-400',
            'panel' => 'bg-violet-50',
            'time' => $schedule->last_reviewed_at ?? $schedule->updated_at,
            'href' => route('reports.gdpr').'#retention-schedules',
        ]);

        $added++;
        if ($added >= $limit) {
            break;
        }
    }
}

function queue_privacy_erasure_job(PrivacyRequest $request): PrivacyErasureJob
{
    return PrivacyErasureJob::query()->firstOrCreate(
        ['privacy_request_id' => $request->id],
        [
            'patient_id' => $request->patient_id,
            'status' => PrivacyErasureJob::STATUS_PENDING,
            'scheduled_at' => now(),
        ],
    );
}

function process_privacy_erasure_job(PrivacyErasureJob $job): bool
{
    $job->update(['status' => PrivacyErasureJob::STATUS_PROCESSING]);

    try {
        $patient = $job->patient ?? $job->privacyRequest?->patient;
        if (!$patient) {
            throw new \RuntimeException('No patient linked to erasure job.');
        }

        $previousName = $patient->name;
        $anonymisedName = 'Erased Service User #'.$patient->id;

        if ($patient->photo_path && Storage::disk('public')->exists($patient->photo_path)) {
            Storage::disk('public')->delete($patient->photo_path);
        }

        $patient->update([
            'name' => $anonymisedName,
            'preferred_name' => null,
            'nhs_number' => null,
            'gp_name' => null,
            'gp_practice' => null,
            'gp_phone' => null,
            'address' => null,
            'latitude' => null,
            'longitude' => null,
            'phone' => null,
            'next_of_kin' => null,
            'next_of_kin_tel' => null,
            'next_of_kin_email' => null,
            'other_relevant_people' => null,
            'social_services_number' => null,
            'social_worker_name' => null,
            'social_worker_contact' => null,
            'commissioner_name' => null,
            'commissioner_contact' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'photo_path' => null,
            'status' => 'archived',
        ]);

        if (Schema::hasTable('care_journal_entries')) {
            CareJournalEntry::query()
                ->where('patient_id', $patient->id)
                ->update(['body' => '[Redacted following erasure request]']);
        }

        $summary = 'Anonymised patient profile for "'.$previousName.'" → "'.$anonymisedName.'". Photo removed; journal bodies redacted.';

        $job->update([
            'status' => PrivacyErasureJob::STATUS_COMPLETED,
            'processed_at' => now(),
            'result_summary' => $summary,
            'error_message' => null,
        ]);

        if ($job->privacyRequest) {
            $notes = trim((string) $job->privacyRequest->outcome_notes);
            $autoNote = 'Erasure job #'.$job->id.' completed at '.now()->format('d M Y H:i').'. '.$summary;
            $job->privacyRequest->update([
                'outcome_notes' => $notes !== '' ? $notes."\n\n".$autoNote : $autoNote,
            ]);
        }

        AuditTrail::record(
            'updated',
            'Processed GDPR erasure for patient #'.$patient->id,
            'privacy_erasure',
            (string) $job->id,
            $anonymisedName,
            ['privacy_request_id' => $job->privacy_request_id],
            ['patient_url_key' => $patient->url_key],
        );

        return true;
    } catch (\Throwable $exception) {
        $job->update([
            'status' => PrivacyErasureJob::STATUS_FAILED,
            'processed_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);

        return false;
    }
}

function generate_incident_reference(): string
{
    $year = now()->format('Y');
    $count = PatientIncident::query()->whereYear('submitted_at', $year)->count() + 1;

    return 'INC-'.$year.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
}

function incident_involves_personal_data(array $data): bool
{
    $impacts = $data['selectedImpacts'] ?? [];
    if (is_array($impacts) && in_array('Personal / confidential data', $impacts, true)) {
        return true;
    }

    $blob = strtolower(implode(' ', array_filter([
        (string) ($data['incidentTitle'] ?? ''),
        (string) ($data['behaviour'] ?? ''),
        (string) ($data['consequence'] ?? ''),
        (string) ($data['immediateOutcome'] ?? ''),
        (string) ($data['lessonsLearnt'] ?? ''),
        (string) ($data['actionsPlanned'] ?? ''),
    ])));

    if ($blob === '') {
        return false;
    }

    return (bool) preg_match(
        '/\b(personal data|data breach|confidential|gdpr|privacy|nhs number|medical record|health data|special category)\b/',
        $blob,
    );
}

function record_patient_incident_submission(Patient $patient, array $data, ?User $reporter): PatientIncident
{
    $incident = PatientIncident::query()->create([
        'patient_id' => $patient->id,
        'reported_by_user_id' => $reporter?->id,
        'reference' => generate_incident_reference(),
        'incident_title' => trim((string) ($data['incidentTitle'] ?? '')) ?: null,
        'incident_date' => !empty($data['incidentDate']) ? Carbon::parse((string) $data['incidentDate'])->toDateString() : null,
        'incident_time' => trim((string) ($data['incidentTime'] ?? '')) ?: null,
        'location' => trim((string) ($data['location'] ?? '')) ?: null,
        'data' => $data,
        'submitted_at' => now(),
    ]);

    IncidentInvestigation::query()->create([
        'patient_incident_id' => $incident->id,
        'investigation_status' => IncidentInvestigation::STATUS_PENDING,
        'due_at' => now()->addDays(7)->toDateString(),
    ]);

    return $incident;
}

function map_incident_investigation(?IncidentInvestigation $investigation): ?array
{
    if (!$investigation) {
        return null;
    }

    $investigator = $investigation->investigator;
    $investigatorName = $investigator
        ? trim((string) ($investigator->name ?: (($investigator->first_name ?? '').' '.($investigator->surname ?? ''))))
        : '';

    $riddorOverdue = $investigation->riddor_reportable
        && $investigation->riddor_reported_at === null
        && $investigation->incident?->submitted_at?->copy()->addHours(72)->isPast();

    $safeguardingPending = $investigation->safeguarding_concern && !$investigation->safeguarding_referral_made;

    return [
        'id' => $investigation->id,
        'status' => $investigation->investigation_status,
        'statusLabel' => Str::of($investigation->investigation_status)->replace('_', ' ')->title()->toString(),
        'investigatorName' => $investigatorName !== '' ? $investigatorName : null,
        'dueAt' => $investigation->due_at?->toDateString(),
        'dueAtLabel' => $investigation->due_at?->format('d M Y'),
        'investigationOverdue' => $investigation->due_at
            && !in_array($investigation->investigation_status, [IncidentInvestigation::STATUS_COMPLETED, IncidentInvestigation::STATUS_CLOSED], true)
            && $investigation->due_at->isPast(),
        'investigationSummary' => $investigation->investigation_summary,
        'rootCause' => $investigation->root_cause,
        'correctiveActions' => $investigation->corrective_actions,
        'riddorReportable' => $investigation->riddor_reportable,
        'riddorCategory' => $investigation->riddor_category,
        'riddorCategoryLabel' => $investigation->riddor_category
            ? Str::of($investigation->riddor_category)->replace('_', ' ')->title()->toString()
            : null,
        'riddorReportedAt' => $investigation->riddor_reported_at?->toIso8601String(),
        'riddorReportedAtLabel' => $investigation->riddor_reported_at?->format('d M Y, H:i'),
        'riddorReference' => $investigation->riddor_reference,
        'riddorOverdue' => $riddorOverdue,
        'safeguardingConcern' => $investigation->safeguarding_concern,
        'safeguardingReferralMade' => $investigation->safeguarding_referral_made,
        'safeguardingReferralAt' => $investigation->safeguarding_referral_at?->toIso8601String(),
        'safeguardingReferralAtLabel' => $investigation->safeguarding_referral_at?->format('d M Y, H:i'),
        'safeguardingAuthority' => $investigation->safeguarding_authority,
        'safeguardingReference' => $investigation->safeguarding_reference,
        'safeguardingPending' => $safeguardingPending,
    ];
}

function map_patient_incident_for_list(PatientIncident $incident): array
{
    $data = $incident->data ?? [];
    $reporter = $incident->reportedBy;

    return [
        'id' => $incident->id,
        'reference' => $incident->reference,
        'title' => $incident->incident_title ?? ($data['incidentTitle'] ?? '-'),
        'patient_name' => $incident->patient?->name ?? 'Unknown',
        'patient_url_key' => $incident->patient?->url_key,
        'reporter' => $reporter?->name ?? ($data['reporterName'] ?? 'Unknown'),
        'incident_date' => $incident->incident_date?->format('Y-m-d') ?? ($data['incidentDate'] ?? '-'),
        'incident_time' => $incident->incident_time ?? ($data['incidentTime'] ?? '-'),
        'location' => $incident->location ?? ($data['location'] ?? '-'),
        'tags' => $data['selectedTags'] ?? ($data['tags'] ?? []),
        'status' => 'Submitted',
        'duration_minutes' => $data['incidentDuration'] ?? ($data['durationMinutes'] ?? null),
        'submitted_at' => $incident->submitted_at->format('d M Y H:i'),
        'investigation' => map_incident_investigation($incident->investigation),
    ];
}

function map_patient_incident_detail(PatientIncident $incident): array
{
    $data = $incident->data ?? [];
    $reporter = $incident->reportedBy;

    return [
        'id' => $incident->id,
        'reference' => $incident->reference,
        'title' => $incident->incident_title ?? ($data['incidentTitle'] ?? '-'),
        'patient_name' => $incident->patient?->name ?? 'Unknown',
        'patient_url_key' => $incident->patient?->url_key,
        'patient_dob' => $incident->patient?->dob ?? '-',
        'patient_address' => $incident->patient?->address ?? '-',
        'reporter' => $data['reporterName'] ?? ($reporter?->name ?? 'Unknown'),
        'incident_date' => $incident->incident_date?->format('Y-m-d') ?? ($data['incidentDate'] ?? '-'),
        'incident_time' => $incident->incident_time ?? ($data['incidentTime'] ?? '-'),
        'location' => $incident->location ?? ($data['location'] ?? '-'),
        'antecedent' => $data['antecedent'] ?? '-',
        'behaviour' => $data['behaviour'] ?? '-',
        'consequence' => $data['consequence'] ?? '-',
        'immediate_outcome' => $data['immediateOutcome'] ?? '-',
        'lessons_learnt' => $data['lessonsLearnt'] ?? '-',
        'new_triggers' => $data['newTriggers'] ?? '-',
        'actions_planned' => $data['actionsPlanned'] ?? '-',
        'tags' => $data['selectedTags'] ?? [],
        'impacts' => $data['selectedImpacts'] ?? [],
        'duration_minutes' => $data['incidentDuration'] ?? null,
        'staff_members' => $data['staffMembers'] ?? [],
        'manager_name' => $data['managerName'] ?? '-',
        'manager_sign_off' => $data['managerSignOff'] ?? false,
        'status' => 'Submitted',
        'submitted_at' => $incident->submitted_at->format('d M Y H:i'),
        'investigation' => map_incident_investigation($incident->investigation),
    ];
}

function append_incident_investigation_care_alerts($careAlerts, int $limit = 2): void
{
    if (!Schema::hasTable('incident_investigations')) {
        return;
    }

    $added = 0;
    $investigations = IncidentInvestigation::query()
        ->whereNotIn('investigation_status', [IncidentInvestigation::STATUS_COMPLETED, IncidentInvestigation::STATUS_CLOSED])
        ->with(['incident.patient:id,name,url_key'])
        ->orderByDesc('created_at')
        ->limit(15)
        ->get();

    foreach ($investigations as $investigation) {
        $incident = $investigation->incident;
        $patientSlug = $incident?->patient?->url_key;
        $overdue = $investigation->due_at && $investigation->due_at->isPast();
        $riddorOverdue = $investigation->riddor_reportable
            && !$investigation->riddor_reported_at
            && $incident?->submitted_at?->copy()->addHours(72)->isPast();

        if (!$overdue && !$riddorOverdue && !($investigation->safeguarding_concern && !$investigation->safeguarding_referral_made)) {
            continue;
        }

        $details = $riddorOverdue
            ? 'RIDDOR reporting may be overdue — '.$incident?->reference
            : ($investigation->safeguarding_concern && !$investigation->safeguarding_referral_made
                ? 'Safeguarding referral pending — '.$incident?->reference
                : 'Investigation overdue — '.$incident?->reference);

        $careAlerts->push([
            'label' => 'INCIDENT',
            'patient' => $incident?->patient?->name ?? 'Unknown',
            'details' => $details,
            'action' => 'Investigate',
            'accent' => $riddorOverdue ? 'border-red-400' : 'border-orange-400',
            'panel' => $riddorOverdue ? 'bg-red-50' : 'bg-orange-50',
            'time' => $investigation->due_at ?? $incident?->submitted_at,
            'patientUrlKey' => $patientSlug,
            'incidentId' => $incident?->id,
            'href' => $incident
                ? route('reports.incidents.show', $incident->id)
                : route('reports.incidents'),
        ]);

        $added++;
        if ($added >= $limit) {
            break;
        }
    }
}

function build_subject_access_export(Patient $patient): array
{
    $profile = map_patient_profile_payload($patient);

    return [
        'exported_at' => now()->toIso8601String(),
        'patient' => [
            'url_key' => $patient->url_key,
            'reference' => $patient->reference,
            'profile' => $profile,
        ],
        'observations' => PatientVital::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->limit(100)
            ->get()
            ->map(fn (PatientVital $v) => map_patient_vital($v))
            ->values()
            ->all(),
        'fluid_records' => PatientFluidRecord::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->limit(100)
            ->get()
            ->map(fn (PatientFluidRecord $r) => map_patient_fluid_record($r))
            ->values()
            ->all(),
        'bowel_records' => PatientBowelRecord::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->limit(100)
            ->get()
            ->map(fn (PatientBowelRecord $r) => map_patient_bowel_record($r))
            ->values()
            ->all(),
        'handovers' => PatientHandover::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->limit(50)
            ->get()
            ->map(fn (PatientHandover $h) => map_patient_handover($h))
            ->values()
            ->all(),
        'wound_assessments' => PatientWoundAssessment::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->limit(50)
            ->get()
            ->map(fn (PatientWoundAssessment $w) => map_patient_wound_assessment($w))
            ->values()
            ->all(),
        'care_journal' => CareJournalEntry::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->limit(50)
            ->get()
            ->map(fn (CareJournalEntry $e) => map_care_journal_entry($e))
            ->values()
            ->all(),
        'medications' => PatientMedication::query()
            ->where('patient_id', $patient->id)
            ->orderBy('name')
            ->get(['name', 'dose', 'route', 'scheduled_time', 'active'])
            ->map(fn ($m) => [
                'name' => $m->name,
                'dose' => $m->dose,
                'route' => $m->route,
                'scheduledTime' => $m->scheduled_time,
                'active' => $m->active,
            ])
            ->values()
            ->all(),
        'schedules' => PatientSchedule::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('start_at')
            ->limit(50)
            ->get()
            ->map(fn ($s) => [
                'startAt' => $s->start_at?->toIso8601String(),
                'endAt' => $s->end_at?->toIso8601String(),
                'status' => $s->status,
                'purpose' => $s->purpose,
            ])
            ->values()
            ->all(),
        'care_plans' => PatientCarePlanForm::query()
            ->where('patient_slug', $patient->url_key)
            ->get(['plan_slug', 'status', 'submitted_at', 'updated_at'])
            ->map(fn ($f) => [
                'planSlug' => $f->plan_slug,
                'status' => $f->status,
                'submittedAt' => $f->submitted_at,
                'updatedAt' => $f->updated_at,
            ])
            ->values()
            ->all(),
    ];
}

function care_plan_catalogue(): array
{
    return [
        ['slug' => 'personal-care-and-dignity', 'title' => 'Personal Care & Dignity', 'default_risks' => ['None']],
        ['slug' => 'mobility-and-moving', 'title' => 'Mobility & Moving / Handling', 'default_risks' => ['Fall Risk']],
        ['slug' => 'nutrition-and-hydration', 'title' => 'Nutrition, Hydration & Dysphagia', 'default_risks' => ['Choking Risk', 'Weight Loss']],
        ['slug' => 'medication-support', 'title' => 'Medication & Treatment', 'default_risks' => ['None']],
        ['slug' => 'pressure-area-care', 'title' => 'Pressure Area Care & Tissue Viability', 'default_risks' => ['None']],
        ['slug' => 'seizure-management', 'title' => 'Seizure Management', 'default_risks' => ['None']],
        ['slug' => 'respiratory-care', 'title' => 'Respiratory Care', 'default_risks' => ['Breathlessness']],
        ['slug' => 'enteral-feeding', 'title' => 'Enteral Feeding', 'default_risks' => ['None']],
        ['slug' => 'diabetes-management', 'title' => 'Diabetes Management', 'default_risks' => ['N/A']],
        ['slug' => 'behaviour-support', 'title' => 'Behaviour Support & Distressed Behaviour', 'default_risks' => ['N/A']],
        ['slug' => 'continence-care', 'title' => 'Catheter & Continence Care', 'default_risks' => ['Skin Breakdown']],
        ['slug' => 'wound-care', 'title' => 'Wound Care', 'default_risks' => ['None']],
        ['slug' => 'sleeping-and-resting', 'title' => 'Sleeping & Night Support', 'default_risks' => ['None']],
        ['slug' => 'community-access', 'title' => 'Community Access & Transport', 'default_risks' => ['None']],
        ['slug' => 'mental-health-and-emotional-wellbeing', 'title' => 'Mental Health & Emotional Wellbeing', 'default_risks' => ['None']],
        ['slug' => 'communication-and-sensory', 'title' => 'Communication & Sensory', 'default_risks' => ['None']],
        ['slug' => 'infection-prevention-and-monitoring', 'title' => 'Infection Prevention & Monitoring', 'default_risks' => ['None']],
        ['slug' => 'bowel-and-stoma-care', 'title' => 'Bowel & Stoma Care', 'default_risks' => ['None']],
        ['slug' => 'pain-management', 'title' => 'Pain Management', 'default_risks' => ['None']],
        ['slug' => 'end-of-life-support', 'title' => 'End of Life / Advance Care Planning', 'default_risks' => ['None']],
    ];
}

function care_plan_status_label(?string $status): string
{
    return match (strtolower(trim((string) $status))) {
        'draft' => 'Draft',
        'reviewed' => 'Under Review',
        'submitted' => 'Active',
        default => 'Not started',
    };
}

function care_plan_risks_from_data(?array $data, array $defaultRisks = ['Not assessed']): array
{
    if (!is_array($data)) {
        return $defaultRisks;
    }

    $raw = trim((string) ($data['linked_risks_rag'] ?? ''));
    if ($raw === '') {
        return $defaultRisks;
    }

    $parts = preg_split('/[,;\n]+/', $raw) ?: [];
    $risks = array_values(array_filter(array_map('trim', $parts)));

    return $risks !== [] ? $risks : $defaultRisks;
}

function build_patient_care_plan_cards(string $patientSlug): array
{
    $forms = PatientCarePlanForm::query()
        ->where('patient_slug', $patientSlug)
        ->get()
        ->keyBy('plan_slug');

    $updaterIds = $forms->pluck('updated_by_user_id')->filter()->unique()->values();
    $updaterNamesById = User::query()
        ->whereIn('id', $updaterIds)
        ->get(['id', 'name', 'first_name', 'surname'])
        ->mapWithKeys(function (User $user) {
            $fullName = trim((string) ($user->name ?? ''));
            if ($fullName === '') {
                $fullName = trim((string) (($user->first_name ?? '').' '.($user->surname ?? '')));
            }

            return [$user->id => ($fullName !== '' ? $fullName : 'Unknown user')];
        });

    return collect(care_plan_catalogue())
        ->map(function (array $plan) use ($forms, $updaterNamesById) {
            $form = $forms->get($plan['slug']);
            $status = care_plan_status_label($form?->status);
            $hasRecord = $form !== null;

            return [
                'slug' => $plan['slug'],
                'title' => $plan['title'],
                'status' => $status,
                'risks' => $hasRecord
                    ? care_plan_risks_from_data($form->data, $plan['default_risks'])
                    : $plan['default_risks'],
                'date' => $hasRecord && $form->updated_at
                    ? $form->updated_at->format('d M Y, H:i')
                    : 'Not yet updated',
                'author' => $hasRecord
                    ? ($updaterNamesById[$form->updated_by_user_id] ?? 'Unknown user')
                    : 'Not yet updated',
                'hasRecord' => $hasRecord,
            ];
        })
        ->values()
        ->all();
}

function care_plan_schema_version(string $planSlug): int
{
    $strictlyValidatedPlans = [
        'personal-care-and-dignity',
        'pressure-area-care',
        'medication-support',
        'seizure-management',
        'respiratory-care',
        'enteral-feeding',
        'diabetes-management',
        'behaviour-support',
        'continence-care',
        'wound-care',
        'sleeping-and-resting',
        'community-access',
        'mental-health-and-emotional-wellbeing',
        'communication-and-sensory',
        'infection-prevention-and-monitoring',
        'bowel-and-stoma-care',
        'pain-management',
        'end-of-life-support',
        'nutrition-and-hydration',
        'mobility-and-moving',
    ];

    return in_array($planSlug, $strictlyValidatedPlans, true) ? 2 : 1;
}

function care_plan_common_generic_rules(): array
{
    return [
        'what_matters_to_me' => ['required', 'string', 'max:5000'],
        'baseline_clinical_summary' => ['required', 'string', 'max:5000'],
        'linked_risks_rag' => ['required', 'string', 'max:1000'],
        'smart_outcomes' => ['required', 'string', 'max:3000'],
        'proactive_support' => ['required', 'string', 'max:3000'],
        'active_steps' => ['required', 'string', 'max:3000'],
        'reactive_steps' => ['required', 'string', 'max:3000'],
        'equipment_required' => ['required', 'string', 'max:1000'],
        'staff_competencies_training_required' => ['required', 'string', 'max:1000'],
        'monitoring_and_recording' => ['required', 'string', 'max:3000'],
        'escalation_pathway' => ['required', 'string', 'max:3000'],
        'capacity_consent_note' => ['required', 'string', 'max:3000'],
        'review_due' => ['required', 'date'],
        'owner' => ['required', 'string', 'max:255'],
    ];
}

function care_plan_primary_focus_field_count(string $planSlug): ?int
{
    return match ($planSlug) {
        'medication-support' => 4,
        'seizure-management' => 4,
        'respiratory-care' => 6,
        'enteral-feeding' => 6,
        'diabetes-management' => 6,
        'behaviour-support' => 6,
        'continence-care' => 5,
        'wound-care' => 5,
        'sleeping-and-resting' => 5,
        'community-access' => 5,
        'mental-health-and-emotional-wellbeing' => 5,
        'communication-and-sensory' => 5,
        'infection-prevention-and-monitoring' => 5,
        'bowel-and-stoma-care' => 5,
        'pain-management' => 5,
        'end-of-life-support' => 5,
        default => null,
    };
}

function care_plan_rules_with_primary_focus(int $fieldCount): array
{
    $rules = care_plan_common_generic_rules();
    for ($i = 0; $i < $fieldCount; $i++) {
        $rules["primary_focus_{$i}"] = ['required', 'string', 'max:1000'];
    }

    return $rules;
}

function care_plan_field_rules(string $planSlug): array
{
    return match ($planSlug) {
        'personal-care-and-dignity' => [
            'cultural_or_religious_preferences' => ['required', 'string', 'max:5000'],
            'privacy_and_consent_requirements' => ['required', 'string', 'max:5000'],
            'assistance_area_0' => ['required', 'boolean'],
            'assistance_area_1' => ['required', 'boolean'],
            'assistance_area_2' => ['required', 'boolean'],
            'assistance_area_3' => ['required', 'boolean'],
            'manual_handling_staff_count' => ['nullable', 'string', 'max:255'],
            'manual_handling_technique' => ['nullable', 'string', 'max:255'],
            'manual_handling_sling_size' => ['nullable', 'string', 'max:255'],
            'manual_handling_notes' => ['nullable', 'string', 'max:2000'],
            'skin_checks_required' => ['required', 'boolean'],
            'what_matters_to_me' => ['required', 'string', 'max:5000'],
            'baseline_clinical_summary' => ['required', 'string', 'max:5000'],
            'smart_outcome_description' => ['required', 'string', 'max:2000'],
            'review_date' => ['required', 'date'],
            'plan_owner' => ['required', 'string', 'max:255'],
            'proactive_support' => ['required', 'string', 'max:3000'],
            'active_steps' => ['required', 'string', 'max:3000'],
            'reactive_steps' => ['required', 'string', 'max:3000'],
            'equipment_required' => ['required', 'string', 'max:3000'],
            'staff_competencies_training_required' => ['required', 'string', 'max:3000'],
            'monitoring_and_recording' => ['required', 'string', 'max:3000'],
            'escalation_pathway' => ['required', 'string', 'max:3000'],
            'capacity_consent_note' => ['required', 'string', 'max:5000'],
        ],
        'nutrition-and-hydration' => [
            'must_score_weight_trend' => ['required', 'string', 'max:1000'],
            'food_preferences_cultural_needs' => ['required', 'string', 'max:1000'],
            'iddsi_food_level' => ['required', 'string', 'max:255'],
            'iddsi_drink_level_thickener_recipe' => ['required', 'string', 'max:1000'],
            'feeding_posture_pacing_swallow_strategies' => ['required', 'string', 'max:1000'],
            'daily_fluid_target_ml' => ['required', 'string', 'max:255'],
            'what_matters_to_me' => ['required', 'string', 'max:5000'],
            'baseline_clinical_summary' => ['required', 'string', 'max:5000'],
            'linked_risks_rag' => ['required', 'string', 'max:1000'],
            'smart_outcomes' => ['required', 'string', 'max:3000'],
            'proactive_support' => ['required', 'string', 'max:3000'],
            'active_steps' => ['required', 'string', 'max:3000'],
            'reactive_steps' => ['required', 'string', 'max:3000'],
            'equipment_required' => ['required', 'string', 'max:1000'],
            'staff_competencies_training_required' => ['required', 'string', 'max:1000'],
            'monitoring_and_recording' => ['required', 'string', 'max:3000'],
            'escalation_pathway' => ['required', 'string', 'max:3000'],
            'capacity_consent_note' => ['required', 'string', 'max:3000'],
            'review_due' => ['required', 'date'],
            'owner' => ['required', 'string', 'max:255'],
        ],
        'mobility-and-moving' => [
            'mobility_baseline_aids_used' => ['required', 'string', 'max:1000'],
            'transfer_types' => ['required', 'string', 'max:1000'],
            'falls_history_physio_programme' => ['required', 'string', 'max:1000'],
            'hoist_type_and_sling' => ['required', 'string', 'max:1000'],
            'staff_transfers_positioning_limits' => ['required', 'string', 'max:1000'],
            'what_matters_to_me' => ['required', 'string', 'max:5000'],
            'baseline_clinical_summary' => ['required', 'string', 'max:5000'],
            'linked_risks_rag' => ['required', 'string', 'max:1000'],
            'smart_outcomes' => ['required', 'string', 'max:3000'],
            'proactive_support' => ['required', 'string', 'max:3000'],
            'active_steps' => ['required', 'string', 'max:3000'],
            'reactive_steps' => ['required', 'string', 'max:3000'],
            'equipment_required' => ['required', 'string', 'max:1000'],
            'staff_competencies_training_required' => ['required', 'string', 'max:1000'],
            'monitoring_and_recording' => ['required', 'string', 'max:3000'],
            'escalation_pathway' => ['required', 'string', 'max:3000'],
            'capacity_consent_note' => ['required', 'string', 'max:3000'],
            'review_due' => ['required', 'date'],
            'owner' => ['required', 'string', 'max:255'],
        ],
        'pressure-area-care' => [
            'waterlow_braden_score_date' => ['required', 'string', 'max:255'],
            'current_wounds_grades_dressings' => ['required', 'string', 'max:1000'],
            'turning_regime_repositioning_frequency' => ['required', 'string', 'max:1000'],
            'mattress_cushion_specification' => ['required', 'string', 'max:1000'],
            'moisture_management_skincare_products' => ['required', 'string', 'max:1000'],
            'what_matters_to_me' => ['required', 'string', 'max:5000'],
            'baseline_clinical_summary' => ['required', 'string', 'max:5000'],
            'linked_risks_rag' => ['required', 'string', 'max:1000'],
            'smart_outcomes' => ['required', 'string', 'max:3000'],
            'proactive_support' => ['required', 'string', 'max:3000'],
            'active_steps' => ['required', 'string', 'max:3000'],
            'reactive_steps' => ['required', 'string', 'max:3000'],
            'equipment_required' => ['required', 'string', 'max:1000'],
            'staff_competencies_training_required' => ['required', 'string', 'max:1000'],
            'monitoring_and_recording' => ['required', 'string', 'max:3000'],
            'escalation_pathway' => ['required', 'string', 'max:3000'],
            'capacity_consent_note' => ['required', 'string', 'max:3000'],
            'review_due' => ['required', 'date'],
            'owner' => ['required', 'string', 'max:255'],
        ],
        'medication-support',
        'seizure-management',
        'respiratory-care',
        'enteral-feeding',
        'diabetes-management',
        'behaviour-support',
        'continence-care',
        'wound-care',
        'sleeping-and-resting',
        'community-access',
        'mental-health-and-emotional-wellbeing',
        'communication-and-sensory',
        'infection-prevention-and-monitoring',
        'bowel-and-stoma-care',
        'pain-management',
        'end-of-life-support' => care_plan_rules_with_primary_focus(care_plan_primary_focus_field_count($planSlug) ?? 0),
        default => [],
    };
}

function validate_care_plan_payload_shape(string $planSlug, array $data): void
{
    $rules = care_plan_field_rules($planSlug);
    if ($rules === []) {
        return;
    }

    $unknownFields = array_diff(array_keys($data), array_keys($rules));
    if ($unknownFields !== []) {
        throw ValidationException::withMessages([
            'data' => 'Submitted payload includes unsupported field(s): '.implode(', ', $unknownFields),
        ]);
    }

    $missingFields = array_diff(array_keys($rules), array_keys($data));
    if ($missingFields !== []) {
        throw ValidationException::withMessages([
            'data' => 'Submitted payload is missing required field(s): '.implode(', ', $missingFields),
        ]);
    }

    validator($data, $rules)->validate();
}

function care_plan_summary_payload(string $planSlug, array $data): array
{
    $keyFields = match ($planSlug) {
        'personal-care-and-dignity' => [
            'plan_owner' => $data['plan_owner'] ?? null,
            'review_date' => $data['review_date'] ?? null,
            'skin_checks_required' => $data['skin_checks_required'] ?? null,
        ],
        'nutrition-and-hydration' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'daily_fluid_target_ml' => $data['daily_fluid_target_ml'] ?? null,
        ],
        'mobility-and-moving' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'hoist_type_and_sling' => $data['hoist_type_and_sling'] ?? null,
        ],
        'pressure-area-care' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'waterlow_braden_score_date' => $data['waterlow_braden_score_date'] ?? null,
        ],
        'medication-support' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'time_critical_medicines' => $data['primary_focus_0'] ?? null,
        ],
        'seizure-management' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'seizure_types_pattern' => $data['primary_focus_0'] ?? null,
        ],
        'respiratory-care' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'airway_status' => $data['primary_focus_0'] ?? null,
        ],
        'enteral-feeding' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'feeding_regimen' => $data['primary_focus_0'] ?? null,
        ],
        'diabetes-management' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'diabetes_type_treatment' => $data['primary_focus_0'] ?? null,
        ],
        'behaviour-support' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'function_of_behaviour' => $data['primary_focus_0'] ?? null,
        ],
        'continence-care' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'continence_type_size' => $data['primary_focus_0'] ?? null,
        ],
        'wound-care' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'wound_type_location' => $data['primary_focus_0'] ?? null,
        ],
        'sleeping-and-resting' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'bedtime_routine' => $data['primary_focus_0'] ?? null,
        ],
        'community-access' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'access_goals' => $data['primary_focus_0'] ?? null,
        ],
        'mental-health-and-emotional-wellbeing' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'mental_health_history' => $data['primary_focus_0'] ?? null,
        ],
        'communication-and-sensory' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'preferred_communication' => $data['primary_focus_0'] ?? null,
        ],
        'infection-prevention-and-monitoring' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'baseline_observations_schedule' => $data['primary_focus_0'] ?? null,
        ],
        'bowel-and-stoma-care' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'bowel_pattern_targets' => $data['primary_focus_0'] ?? null,
        ],
        'pain-management' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'pain_sites_patterns' => $data['primary_focus_0'] ?? null,
        ],
        'end-of-life-support' => [
            'owner' => $data['owner'] ?? null,
            'review_due' => $data['review_due'] ?? null,
            'advance_statement_preferences' => $data['primary_focus_0'] ?? null,
        ],
        default => [],
    };

    $excerptSource = array_filter([
        $data['what_matters_to_me'] ?? null,
        $data['baseline_clinical_summary'] ?? null,
        $data['smart_outcome_description'] ?? null,
        $data['capacity_consent_note'] ?? null,
    ], fn ($value) => is_string($value) && trim($value) !== '');

    $excerpt = implode(' | ', $excerptSource);
    if (mb_strlen($excerpt) > 1000) {
        $excerpt = mb_substr($excerpt, 0, 1000);
    }

    return [
        'key_fields' => $keyFields,
        'data_excerpt' => $excerpt !== '' ? $excerpt : null,
    ];
}

function normalize_alert_analysis_percentages(int $resolved, int $missed, int $flagged): array
{
    $total = $resolved + $missed + $flagged;
    if ($total === 0) {
        return [
            'resolved' => 0,
            'missed' => 0,
            'flagged' => 0,
            'total' => 0,
            'resolvedCount' => 0,
            'missedCount' => 0,
            'flaggedCount' => 0,
        ];
    }

    $resolvedPct = (int) round(100 * $resolved / $total);
    $missedPct = (int) round(100 * $missed / $total);
    $flaggedPct = (int) round(100 * $flagged / $total);
    $adjust = 100 - ($resolvedPct + $missedPct + $flaggedPct);

    if ($adjust !== 0) {
        if ($resolved >= $missed && $resolved >= $flagged) {
            $resolvedPct += $adjust;
        } elseif ($missed >= $flagged) {
            $missedPct += $adjust;
        } else {
            $flaggedPct += $adjust;
        }
    }

    return [
        'resolved' => $resolvedPct,
        'missed' => $missedPct,
        'flagged' => $flaggedPct,
        'total' => $total,
        'resolvedCount' => $resolved,
        'missedCount' => $missed,
        'flaggedCount' => $flagged,
    ];
}

function alerts_analysis_drill_down_href(string $key, Carbon $startOfWeek, Carbon $endOfWeek, ?User $user): string
{
    $from = $startOfWeek->format('Y-m-d');
    $to = $endOfWeek->format('Y-m-d');
    $canViewReports = AuditTrail::canViewReports($user);

    return match ($key) {
        'medications' => $canViewReports
            ? route('reports.medications', ['from' => $from, 'to' => $to])
            : route('care-alerts'),
        'personal_care' => $canViewReports
            ? route('reports.schedules', ['from' => $from, 'to' => $to])
            : route('schedules'),
        'observations' => $canViewReports
            ? route('reports.clinical-outcomes', ['from' => $from, 'to' => $to])
            : route('care-alerts'),
        default => route('dashboard'),
    };
}

function apply_risk_assessment_snapshot(PatientRiskAssessment $assessment, array $snapshot, User $user): void
{
    $lastReviewed = isset($snapshot['last_reviewed_at'])
        ? Carbon::parse((string) $snapshot['last_reviewed_at'])->toDateString()
        : now()->toDateString();
    $cycleMonths = (int) ($snapshot['review_cycle_months'] ?? 3);
    $nextDue = isset($snapshot['next_review_due_at'])
        ? Carbon::parse((string) $snapshot['next_review_due_at'])->toDateString()
        : Carbon::parse($lastReviewed)->addMonths($cycleMonths)->toDateString();

    $assessment->update([
        'risk_level' => $snapshot['risk_level'] ?? $assessment->risk_level,
        'status' => $snapshot['status'] ?? $assessment->status,
        'triggers' => $snapshot['triggers'] ?? null,
        'current_controls' => $snapshot['current_controls'] ?? null,
        'mitigation_plan' => $snapshot['mitigation_plan'] ?? null,
        'owner_name' => $snapshot['owner_name'] ?? null,
        'last_reviewed_at' => $lastReviewed,
        'next_review_due_at' => $nextDue,
        'review_cycle_months' => $cycleMonths,
        'reviewed_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);
}

function record_risk_assessment_version_forced(PatientRiskAssessment $assessment, ?User $user, string $changeSummary, bool $alwaysRecord = false): void
{
    if (!Schema::hasTable('patient_risk_assessment_versions')) {
        return;
    }

    $snapshot = risk_assessment_snapshot_from_model($assessment);
    $latest = PatientRiskAssessmentVersion::query()
        ->where('patient_risk_assessment_id', $assessment->id)
        ->orderByDesc('recorded_at')
        ->first();

    if (!$alwaysRecord && $latest?->snapshot === $snapshot) {
        return;
    }

    PatientRiskAssessmentVersion::query()->create([
        'patient_risk_assessment_id' => $assessment->id,
        'patient_id' => $assessment->patient_id,
        'risk_slug' => $assessment->risk_slug,
        'snapshot' => $snapshot,
        'change_summary' => $changeSummary,
        'recorded_by_user_id' => $user?->id,
        'recorded_at' => now(),
    ]);
}

function build_dashboard_alerts_analysis(Carbon $startOfWeek, Carbon $endOfWeek, Carbon $now, ?User $user = null): array
{
    $medResolved = MedicationAdministration::query()
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->whereIn('status', ['given', 'self_administered'])
        ->count();
    $medFlagged = MedicationAdministration::query()
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->whereIn('status', ['refused', 'omitted', 'delayed'])
        ->count();
    $medMissed = MedicationReminder::query()
        ->whereBetween('due_at', [$startOfWeek, $endOfWeek])
        ->where('dismissed', false)
        ->where('due_at', '<', $now)
        ->count();

    $weekScheduleIds = PatientSchedule::query()
        ->whereBetween('start_at', [$startOfWeek, $endOfWeek])
        ->pluck('id');

    $careResolved = ScheduleVisitTask::query()
        ->whereIn('patient_schedule_id', $weekScheduleIds)
        ->where('outcome', 'completed')
        ->count();
    $careFlagged = ScheduleVisitTask::query()
        ->whereIn('patient_schedule_id', $weekScheduleIds)
        ->whereIn('outcome', ['refused', 'unable', 'escalated'])
        ->count();

    $pastScheduleIds = PatientSchedule::query()
        ->whereIn('id', $weekScheduleIds)
        ->where('end_at', '<', $now)
        ->pluck('id');

    $careMissed = ScheduleVisitTask::query()
        ->whereIn('patient_schedule_id', $pastScheduleIds)
        ->whereNull('outcome')
        ->count();

    $vitals = PatientVital::query()
        ->whereBetween('recorded_at', [$startOfWeek, $endOfWeek])
        ->get();

    $obsResolved = 0;
    $obsFlagged = 0;
    foreach ($vitals as $vital) {
        if (!empty(evaluate_vital_threshold_alerts($vital))) {
            $obsFlagged++;
        } else {
            $obsResolved++;
        }
    }

    $obsMissed = ScheduleVisitTask::query()
        ->whereIn('patient_schedule_id', $pastScheduleIds)
        ->where('task_key', 'observations')
        ->whereNull('outcome')
        ->count();

    $rows = [
        ['key' => 'medications', 'label' => 'Medications', 'resolved' => $medResolved, 'missed' => $medMissed, 'flagged' => $medFlagged],
        ['key' => 'personal_care', 'label' => 'Personal Care', 'resolved' => $careResolved, 'missed' => $careMissed, 'flagged' => $careFlagged],
        ['key' => 'observations', 'label' => 'Observations', 'resolved' => $obsResolved, 'missed' => $obsMissed, 'flagged' => $obsFlagged],
    ];

    return array_map(function (array $row) use ($startOfWeek, $endOfWeek, $user) {
        return array_merge(
            [
                'key' => $row['key'],
                'label' => $row['label'],
                'drillDownHref' => alerts_analysis_drill_down_href($row['key'], $startOfWeek, $endOfWeek, $user),
            ],
            normalize_alert_analysis_percentages($row['resolved'], $row['missed'], $row['flagged']),
        );
    }, $rows);
}

} // route helper functions (guarded for test / multi-bootstrap)

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    $now = now();
    $startOfWeek = $now->copy()->startOfWeek(Carbon::SUNDAY);
    $endOfWeek = $now->copy()->endOfWeek(Carbon::SATURDAY);

    $weeklySchedules = PatientSchedule::query()
        ->whereBetween('start_at', [$startOfWeek, $endOfWeek])
        ->get();

    $weeklyVisitsTotal = $weeklySchedules->count();
    $weeklyVisitsCompleted = $weeklySchedules->where('status', 'completed')->count();
    $weeklyVisitsMissed = $weeklySchedules->where('status', 'missed')->count();
    $weeklyVisitsInProgress = $weeklySchedules->filter(function ($s) use ($now) {
        return !$s->status && $s->start_at->lte($now) && $s->end_at->gte($now);
    })->count();
    $weeklyVisitsUpcoming = $weeklySchedules->filter(function ($s) use ($now) {
        return !$s->status && $s->start_at->gt($now);
    })->count();

    $weeklyCarePlanUpdates = PatientCarePlanForm::query()
        ->whereBetween('updated_at', [$startOfWeek, $endOfWeek])
        ->count();
    $weeklyDocumentUpdates = PatientDocumentForm::query()
        ->whereBetween('updated_at', [$startOfWeek, $endOfWeek])
        ->count();
    $weeklyVitals = PatientVital::query()
        ->whereBetween('recorded_at', [$startOfWeek, $endOfWeek])
        ->count();
    $weeklyTasksTotal = $weeklyCarePlanUpdates + $weeklyDocumentUpdates + $weeklyVitals;
    $weeklyTasksCompleted = $weeklyCarePlanUpdates + $weeklyDocumentUpdates;
    $weeklyTasksPartial = $weeklyVitals;
    $weeklyTasksMissed = max(0, $weeklyTasksTotal - $weeklyTasksCompleted - $weeklyTasksPartial);

    $carePlanDrafts = PatientCarePlanForm::query()->where('status', 'draft')->count();
    $inactiveEmployees = User::query()
        ->whereRaw('LOWER(COALESCE(account_status, ?)) = ?', ['active', 'inactive'])
        ->count();
    $upcomingBookings = PatientSchedule::query()
        ->where('start_at', '>=', $now)
        ->count();

    $careAlerts = build_active_care_alerts();
    $totalCareAlerts = $careAlerts->count();
    $alertPatients = $careAlerts
        ->take(4)
        ->map(fn ($a) => collect($a)->except('time')->all())
        ->values();

    $recentJournalEntries = Schema::hasTable('care_journal_entries')
        ? CareJournalEntry::query()
            ->with(['patient:id,name,url_key', 'author:id,name,first_name,surname'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (CareJournalEntry $entry) => map_care_journal_entry($entry))
            ->values()
        : collect();

    $alertsAnalysis = build_dashboard_alerts_analysis($startOfWeek, $endOfWeek, $now, request()->user());

    return Inertia::render('Dashboard', [
        'recentJournalEntries' => $recentJournalEntries,
        'dashboardStats' => [
            'visits' => [
                'total' => $weeklyVisitsTotal,
                'metrics' => [
                    'complete' => $weeklyVisitsCompleted,
                    'inProgress' => $weeklyVisitsInProgress,
                    'upcoming' => $weeklyVisitsUpcoming,
                    'missed' => $weeklyVisitsMissed,
                ],
            ],
            'tasks' => [
                'total' => $weeklyTasksTotal,
                'metrics' => [
                    'complete' => $weeklyTasksCompleted,
                    'partial' => $weeklyTasksPartial,
                    'missed' => $weeklyTasksMissed,
                ],
            ],
            'operations' => [
                'assessmentsInProgress' => $carePlanDrafts,
                'supervisions' => $inactiveEmployees,
                'bookings' => $upcomingBookings,
            ],
            'careAlerts' => $alertPatients,
            'totalCareAlerts' => $totalCareAlerts,
            'alertsAnalysis' => $alertsAnalysis,
            'alertsAnalysisPeriodLabel' => $startOfWeek->format('d M').' – '.$endOfWeek->format('d M Y'),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/csrf-token', function (Request $request) {
    $request->session()->regenerateToken();

    return response()->json([
        'token' => csrf_token(),
    ]);
})->middleware('web')->name('csrf.token');

Route::get('/analytics', function () {
    $now = now();
    $startOfWeek = $now->copy()->startOfWeek(Carbon::SUNDAY);
    $endOfWeek = $now->copy()->endOfWeek(Carbon::SATURDAY);

    $weeklySchedules = PatientSchedule::query()
        ->whereBetween('start_at', [$startOfWeek, $endOfWeek])
        ->with(['patient:id,name,url_key', 'assignedUser:id,name'])
        ->get();

    $visitsTotal = $weeklySchedules->count();
    $visitsCompleted = $weeklySchedules->where('status', 'completed')->count();
    $visitsMissed = $weeklySchedules->where('status', 'missed')->count();
    $visitsInProgress = $weeklySchedules->filter(function (PatientSchedule $schedule) use ($now) {
        return ! $schedule->status
            && optional($schedule->start_at)->lte($now)
            && optional($schedule->end_at)->gte($now);
    })->count();
    $visitsUpcoming = $weeklySchedules->filter(function (PatientSchedule $schedule) use ($now) {
        return ! $schedule->status && optional($schedule->start_at)->gt($now);
    })->count();
    $visitsOverdue = $weeklySchedules->filter(function (PatientSchedule $schedule) use ($now) {
        return ! $schedule->status && optional($schedule->end_at)->lt($now);
    })->count();

    $overdueMedicationAlerts = MedicationReminder::query()
        ->where('dismissed', false)
        ->where('due_at', '<', $now)
        ->count();
    $refusedMedicationAlerts = MedicationAdministration::query()
        ->whereDate('created_at', $now->toDateString())
        ->whereIn('status', ['refused', 'omitted'])
        ->count();
    $highRiskPatients = Patient::query()
        ->whereIn(DB::raw('LOWER(COALESCE(rag_status, ""))'), ['red', 'amber'])
        ->count();

    $careAlerts = [
        ['label' => 'Missed medication reminders', 'value' => $overdueMedicationAlerts],
        ['label' => 'Refused / omitted medications today', 'value' => $refusedMedicationAlerts],
        ['label' => 'Overdue visits pending follow-up', 'value' => $visitsOverdue],
        ['label' => 'High/elevated risk patients', 'value' => $highRiskPatients],
    ];

    $dailyVisitTrend = collect(range(6, 0))
        ->map(function (int $daysAgo) use ($now) {
            $dayStart = $now->copy()->subDays($daysAgo)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();

            $daySchedules = PatientSchedule::query()
                ->whereBetween('start_at', [$dayStart, $dayEnd])
                ->get();

            $completed = $daySchedules->where('status', 'completed')->count();
            $missed = $daySchedules->where('status', 'missed')->count();
            $inProgress = $daySchedules->filter(function (PatientSchedule $schedule) use ($now) {
                return ! $schedule->status
                    && optional($schedule->start_at)->lte($now)
                    && optional($schedule->end_at)->gte($now);
            })->count();
            $upcoming = $daySchedules->filter(function (PatientSchedule $schedule) use ($now) {
                return ! $schedule->status && optional($schedule->start_at)->gt($now);
            })->count();
            $overdue = $daySchedules->filter(function (PatientSchedule $schedule) use ($now) {
                return ! $schedule->status && optional($schedule->end_at)->lt($now);
            })->count();

            return [
                'label' => $dayStart->format('D d M'),
                'shortLabel' => $dayStart->format('D'),
                'total' => $daySchedules->count(),
                'completed' => $completed,
                'missed' => $missed,
                'in_progress' => $inProgress,
                'upcoming' => $upcoming,
                'overdue' => $overdue,
            ];
        })
        ->values()
        ->all();

    $visitStatusTotals = [
        'completed' => $visitsCompleted,
        'missed' => $visitsMissed,
        'in_progress' => $visitsInProgress,
        'upcoming' => $visitsUpcoming,
        'overdue' => $visitsOverdue,
    ];

    $recentMissedShifts = PatientSchedule::query()
        ->with(['patient:id,name,url_key', 'assignedUser:id,name'])
        ->where('status', 'missed')
        ->latest('end_at')
        ->limit(10)
        ->get()
        ->map(function (PatientSchedule $schedule) {
            return [
                'id' => $schedule->id,
                'patient' => $schedule->patient?->name ?? 'Unknown',
                'staff' => $schedule->assignedUser?->name ?? 'Unassigned',
                'window' => optional($schedule->start_at)->format('d M Y H:i')
                    .' - '.optional($schedule->end_at)->format('H:i'),
                'notes' => $schedule->notes,
            ];
        })
        ->values()
        ->all();

    return Inertia::render('Analytics', [
        'summary' => [
            'visitsTotal' => $visitsTotal,
            'visitsCompleted' => $visitsCompleted,
            'visitsMissed' => $visitsMissed,
            'visitsInProgress' => $visitsInProgress,
            'visitsUpcoming' => $visitsUpcoming,
            'visitsOverdue' => $visitsOverdue,
            'totalCareAlerts' => array_sum(array_column($careAlerts, 'value')),
        ],
        'careAlerts' => $careAlerts,
        'dailyVisitTrend' => $dailyVisitTrend,
        'visitStatusTotals' => $visitStatusTotals,
        'recentMissedShifts' => $recentMissedShifts,
    ]);
})->middleware(['auth', 'verified'])->name('analytics');

Route::get('/care-alerts', function () {
    $allAlerts = build_active_care_alerts(includeAllMedicationAlerts: true)
        ->map(fn ($a) => collect($a)->except('time')->all())
        ->values();

    return Inertia::render('CareAlerts', ['alerts' => $allAlerts]);
})->middleware(['auth', 'verified'])->name('care-alerts');

if (!function_exists('normalize_patient_allergy_details')) {
function normalize_patient_allergy_details(?string $severeAllergies, ?array $structured = null): array
{
    if (is_array($structured) && !empty($structured)) {
        return collect($structured)
            ->map(function ($row) {
                if (!is_array($row)) {
                    return null;
                }
                $allergen = trim((string) ($row['allergen'] ?? ''));
                if ($allergen === '') {
                    return null;
                }

                return [
                    'allergen' => $allergen,
                    'reaction' => trim((string) ($row['reaction'] ?? '')) ?: null,
                    'severity' => trim((string) ($row['severity'] ?? '')) ?: null,
                    'verified_at' => trim((string) ($row['verified_at'] ?? '')) ?: null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    $text = trim((string) $severeAllergies);
    if ($text === '') {
        return [];
    }

    return collect(preg_split('/[,;]+/', $text) ?: [])
        ->map(fn ($part) => trim((string) $part))
        ->filter(fn ($part) => $part !== '' && strtolower($part) !== 'none')
        ->map(fn ($allergen) => [
            'allergen' => $allergen,
            'reaction' => null,
            'severity' => null,
            'verified_at' => null,
        ])
        ->values()
        ->all();
}

function map_patient_profile_payload(Patient $patient): array
{
    $allergyDetails = is_array($patient->allergy_details) ? $patient->allergy_details : [];
    $legacyAllergies = is_array($patient->allergies) ? array_values(array_filter($patient->allergies)) : [];

    return [
        'name' => $patient->name,
        'preferredName' => $patient->preferred_name,
        'nhsNumber' => $patient->nhs_number,
        'dob' => $patient->dob,
        'address' => $patient->address,
        'phone' => $patient->phone,
        'status' => $patient->status,
        'ragStatus' => $patient->rag_status,
        'staffingRatio' => $patient->staffing_ratio,
        'primaryDiagnosis' => $patient->primary_diagnosis,
        'gpName' => $patient->gp_name,
        'gpPractice' => $patient->gp_practice,
        'gpPhone' => $patient->gp_phone,
        'primaryLanguage' => $patient->primary_language,
        'interpreterRequired' => (bool) $patient->interpreter_required,
        'capacityStatus' => $patient->capacity_status,
        'bestInterestDecision' => $patient->best_interest_decision,
        'informationSharingConsent' => $patient->information_sharing_consent,
        'dolsLpsStatus' => $patient->dols_lps_status,
        'dnacprStatus' => $patient->dnacpr_status,
        'allergies' => $legacyAllergies,
        'allergyDetails' => $allergyDetails,
        'mobilityAids' => $patient->mobility_aids,
        'hoistType' => $patient->hoist_type,
        'slingSize' => $patient->sling_size,
        'equipmentNotes' => $patient->equipment_notes,
        'environmentalNotes' => $patient->environmental_notes,
        'nextOfKin' => $patient->next_of_kin,
        'nextOfKinTel' => $patient->next_of_kin_tel,
        'nextOfKinEmail' => $patient->next_of_kin_email,
        'otherRelevantPeople' => $patient->other_relevant_people,
        'socialServicesNumber' => $patient->social_services_number,
        'socialWorkerName' => $patient->social_worker_name,
        'socialWorkerContact' => $patient->social_worker_contact,
        'commissionerName' => $patient->commissioner_name,
        'commissionerContact' => $patient->commissioner_contact,
        'emergencyContactName' => $patient->emergency_contact_name,
        'emergencyContactPhone' => $patient->emergency_contact_phone,
        'photoUrl' => $patient->photo_path ? route('patients.photo', ['patientRecord' => $patient->id]) : null,
        'updatedAt' => optional($patient->updated_at)->format('d M Y H:i'),
    ];
}

function evaluate_vital_threshold_alerts(PatientVital $vital): array
{
    $alerts = [];

    if ($vital->spo2 !== null) {
        if ($vital->spo2 < 90) {
            $alerts[] = 'Critical SpO₂: '.$vital->spo2.'%';
        } elseif ($vital->spo2 < 94) {
            $alerts[] = 'Low SpO₂: '.$vital->spo2.'%';
        }
    }

    if ($vital->bp_systolic !== null && ($vital->bp_systolic > 180 || $vital->bp_systolic < 90)) {
        $alerts[] = 'BP systolic out of range: '.$vital->bp_systolic.' mmHg';
    }

    if ($vital->heart_rate !== null && ($vital->heart_rate > 100 || $vital->heart_rate < 50)) {
        $alerts[] = 'Heart rate out of range: '.$vital->heart_rate.' bpm';
    }

    if ($vital->temperature_celsius !== null) {
        $temp = (float) $vital->temperature_celsius;
        if ($temp > 38.0 || $temp < 35.0) {
            $alerts[] = 'Temperature out of range: '.$temp.' °C';
        }
    }

    if ($vital->blood_glucose_mmol !== null) {
        $glucose = (float) $vital->blood_glucose_mmol;
        if ($glucose > 11.0 || $glucose < 4.0) {
            $alerts[] = 'Blood glucose out of range: '.$glucose.' mmol/L';
        }
    }

    if ($vital->pain_score !== null && $vital->pain_score >= 7) {
        $alerts[] = 'High pain score: '.$vital->pain_score.'/10';
    }

    return $alerts;
}

function append_vital_threshold_care_alerts($careAlerts, int $limit = 4): void
{
    $added = 0;
    $recentVitals = PatientVital::query()
        ->where('recorded_at', '>=', now()->subDay())
        ->with('patient:id,name,url_key')
        ->orderByDesc('recorded_at')
        ->limit(30)
        ->get();

    foreach ($recentVitals as $vital) {
        $thresholdAlerts = evaluate_vital_threshold_alerts($vital);
        if (empty($thresholdAlerts)) {
            continue;
        }

        $patientSlug = $vital->patient?->url_key;
        $isCritical = collect($thresholdAlerts)->contains(
            fn ($message) => str_contains(strtolower($message), 'critical')
        );

        $careAlerts->push([
            'label' => 'OBSERVATION ALERT',
            'patient' => $vital->patient?->name ?? 'Unknown',
            'details' => implode('; ', array_slice($thresholdAlerts, 0, 2)),
            'action' => 'Review',
            'accent' => $isCritical ? 'border-red-400' : 'border-amber-400',
            'panel' => $isCritical ? 'bg-red-50' : 'bg-amber-50',
            'time' => $vital->recorded_at ?? $vital->created_at,
            'patientUrlKey' => $patientSlug,
            'href' => $patientSlug ? route('patients.observations', $patientSlug) : null,
        ]);

        $added++;
        if ($added >= $limit) {
            break;
        }
    }
}

function build_active_care_alerts(bool $includeAllMedicationAlerts = false): \Illuminate\Support\Collection
{
    $careAlerts = collect();

    $overdueRemindersQuery = MedicationReminder::query()
        ->where('dismissed', false)
        ->where('due_at', '<', now())
        ->whereDate('due_at', now()->toDateString())
        ->with(['patient:id,name,url_key', 'medication:id,name,dose'])
        ->orderByDesc('due_at');

    if (!$includeAllMedicationAlerts) {
        $overdueRemindersQuery->limit(4);
    }

    $overdueReminders = $overdueRemindersQuery->get();

    foreach ($overdueReminders as $reminder) {
        $patientSlug = $reminder->patient?->url_key;
        $careAlerts->push([
            'label' => 'MISSED MEDICATION',
            'patient' => $reminder->patient?->name ?? 'Unknown',
            'details' => ($reminder->medication?->name ?? 'Unknown').' '.($reminder->medication?->dose ?? '').', due '.$reminder->due_at->format(
                $includeAllMedicationAlerts ? 'H:i d M' : 'H:i'
            ),
            'action' => 'Resolve',
            'accent' => 'border-red-400',
            'panel' => 'bg-red-50',
            'time' => $reminder->due_at,
            'patientUrlKey' => $patientSlug,
            'href' => $patientSlug ? route('patients.mar.show', ['patient' => $patientSlug, 'mar' => 'today-mar']) : null,
        ]);
    }

    $refusedTodayQuery = MedicationAdministration::query()
        ->whereIn('status', ['refused', 'omitted', 'delayed'])
        ->whereDate('created_at', now()->toDateString())
        ->with(['patient:id,name,url_key', 'medication:id,name'])
        ->orderByDesc('created_at');

    if (!$includeAllMedicationAlerts) {
        $refusedTodayQuery->limit(4);
    }

    $refusedToday = $refusedTodayQuery->get();

    foreach ($refusedToday as $admin) {
        $patientSlug = $admin->patient?->url_key;
        $careAlerts->push([
            'label' => strtoupper($admin->status).' MEDICATION',
            'patient' => $admin->patient?->name ?? 'Unknown',
            'details' => ($admin->medication?->name ?? 'Unknown').($admin->reason ? ' — '.$admin->reason : ''),
            'action' => 'Review',
            'accent' => $admin->status === 'refused' ? 'border-amber-400' : 'border-orange-400',
            'panel' => $admin->status === 'refused' ? 'bg-amber-50' : 'bg-orange-50',
            'time' => $admin->created_at,
            'patientUrlKey' => $patientSlug,
            'href' => $patientSlug ? route('patients.mar.show', ['patient' => $patientSlug, 'mar' => 'today-mar']) : null,
        ]);
    }

    append_vital_threshold_care_alerts($careAlerts);
    append_wound_escalation_care_alerts($careAlerts);
    append_wound_review_care_alerts($careAlerts);
    append_risk_review_care_alerts($careAlerts);
    append_incident_investigation_care_alerts($careAlerts);
    append_data_retention_care_alerts($careAlerts);
    append_privacy_breach_care_alerts($careAlerts);

    $overdueSchedules = PatientSchedule::query()
        ->where('end_at', '<', now())
        ->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '');
        })
        ->with('patient:id,name,url_key')
        ->orderByDesc('end_at')
        ->get();

    foreach ($overdueSchedules as $schedule) {
        $patientSlug = $schedule->patient?->url_key;
        $careAlerts->push([
            'label' => 'MISSED VISIT',
            'patient' => $schedule->patient?->name ?? 'Unknown',
            'details' => ($schedule->purpose ?? 'Scheduled visit').' — ended '.$schedule->end_at->format(
                $includeAllMedicationAlerts ? 'H:i d M' : 'H:i'
            ),
            'action' => 'Follow Up',
            'accent' => 'border-rose-400',
            'panel' => 'bg-rose-50',
            'time' => $schedule->end_at,
            'patientUrlKey' => $patientSlug,
            'href' => route('schedules'),
        ]);
    }

    $redAmberQuery = Patient::query()
        ->whereIn(DB::raw('LOWER(COALESCE(rag_status, ""))'), ['red', 'amber'])
        ->orderByRaw("CASE WHEN LOWER(rag_status) = 'red' THEN 0 ELSE 1 END");

    if (!$includeAllMedicationAlerts) {
        $redAmberQuery->limit(2);
    }

    $redAmberPatients = $redAmberQuery->get(['name', 'rag_status', 'status', 'url_key']);

    foreach ($redAmberPatients as $patient) {
        $severity = strtolower((string) ($patient->rag_status ?? 'amber'));
        $isRed = $severity === 'red';
        $careAlerts->push([
            'label' => $isRed ? 'HIGH RISK PATIENT' : 'ELEVATED RISK PATIENT',
            'patient' => $patient->name ?: 'Unknown patient',
            'details' => $patient->status ? 'Status: '.$patient->status : 'Requires clinical review',
            'action' => $isRed ? 'Review Now' : 'Review',
            'accent' => $isRed ? 'border-red-400' : 'border-amber-400',
            'panel' => $isRed ? 'bg-red-50' : 'bg-amber-50',
            'time' => now(),
            'patientUrlKey' => $patient->url_key,
            'href' => $patient->url_key ? route('patients.show', $patient->url_key) : null,
        ]);
    }

    return $careAlerts->sortByDesc('time')->values();
}

function build_patient_profile_alert_messages(Patient $patient): array
{
    $messages = [];

    $overdueReminders = MedicationReminder::query()
        ->where('patient_id', $patient->id)
        ->where('dismissed', false)
        ->where('due_at', '<', now())
        ->whereDate('due_at', now()->toDateString())
        ->with('medication:id,name,dose')
        ->orderByDesc('due_at')
        ->limit(5)
        ->get();

    foreach ($overdueReminders as $reminder) {
        $messages[] = 'Overdue medication: '.($reminder->medication?->name ?? 'Unknown')
            .' (due '.$reminder->due_at->format('H:i').').';
    }

    $refusedToday = MedicationAdministration::query()
        ->where('patient_id', $patient->id)
        ->whereIn('status', ['refused', 'omitted', 'delayed'])
        ->whereDate('created_at', now()->toDateString())
        ->with('medication:id,name')
        ->orderByDesc('created_at')
        ->limit(3)
        ->get();

    foreach ($refusedToday as $admin) {
        $label = ucfirst((string) $admin->status);
        $messages[] = "{$label} medication today: ".($admin->medication?->name ?? 'Unknown')
            .($admin->reason ? ' — '.$admin->reason : '');
    }

    $missedVisits = PatientSchedule::query()
        ->where('patient_id', $patient->id)
        ->where('end_at', '<', now())
        ->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '');
        })
        ->orderByDesc('end_at')
        ->limit(2)
        ->get();

    foreach ($missedVisits as $schedule) {
        $messages[] = 'Missed visit window ended '.$schedule->end_at->format('d M H:i').'.';
    }

    if (in_array(strtolower((string) $patient->rag_status), ['red', 'amber'], true)) {
        $messages[] = 'RAG status is '.strtoupper((string) $patient->rag_status).' — review care plan and staffing.';
    }

    $latestVital = PatientVital::query()
        ->where('patient_id', $patient->id)
        ->orderByDesc('recorded_at')
        ->orderByDesc('id')
        ->first();

    if ($latestVital) {
        foreach (evaluate_vital_threshold_alerts($latestVital) as $alert) {
            $messages[] = $alert;
        }
    }

    if (Schema::hasTable('patient_wound_assessments')) {
        $latestWound = PatientWoundAssessment::query()
            ->where('patient_id', $patient->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if ($latestWound) {
            foreach (evaluate_wound_assessment_alerts($latestWound) as $alert) {
                $messages[] = $alert;
            }
        }
    }

    if (Schema::hasTable('patient_fluid_records')) {
        foreach (evaluate_fluid_balance_alerts($patient) as $alert) {
            $messages[] = $alert;
        }
    }

    return array_values(array_unique($messages));
}

function mar_status_abbreviation(?string $status): string
{
    return match (strtolower((string) $status)) {
        'given' => 'G',
        'delayed' => 'D',
        'refused' => 'R',
        'omitted' => 'O',
        'self_administered' => 'S',
        default => '—',
    };
}

function build_patient_mar_chart_rows(Patient $patient, Carbon $month): array
{
    $monthStart = $month->copy()->startOfMonth()->startOfDay();
    $monthEnd = $month->copy()->endOfMonth()->endOfDay();
    $daysInMonth = $month->daysInMonth;

    $medications = PatientMedication::query()
        ->where('patient_id', $patient->id)
        ->where('active', true)
        ->orderBy('scheduled_time')
        ->orderBy('name')
        ->get();

    $administrations = MedicationAdministration::query()
        ->where('patient_id', $patient->id)
        ->whereBetween('created_at', [$monthStart, $monthEnd])
        ->orderByDesc('id')
        ->get()
        ->groupBy(fn ($row) => $row->patient_medication_id.'|'.$row->created_at->format('Y-m-d'));

    return $medications->map(function ($medication) use ($daysInMonth, $month, $administrations) {
        $cells = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateKey = $month->copy()->day($day)->format('Y-m-d');
            $bucketKey = $medication->id.'|'.$dateKey;
            $status = $administrations->get($bucketKey)?->first()?->status;
            $cells[] = mar_status_abbreviation($status);
        }

        $timeLabel = '';
        if (!empty($medication->scheduled_time)) {
            $timeLabel = Carbon::createFromFormat('H:i:s', (string) $medication->scheduled_time)->format('H:i');
        }

        return [
            'name' => $medication->name,
            'dose' => $medication->dose,
            'time' => $timeLabel,
            'cells' => $cells,
        ];
    })->values()->all();
}
} // patient profile helpers

if (!function_exists('format_care_journal_author_name')) {
function format_care_journal_author_name(?User $user): string
{
    if ($user === null) {
        return 'Unknown staff';
    }

    $fullName = trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))));

    return $fullName !== '' ? $fullName : 'Unknown staff';
}

function map_care_journal_entry(CareJournalEntry $entry): array
{
    $patient = $entry->patient;
    $author = $entry->author;

    return [
        'id' => $entry->id,
        'body' => $entry->body,
        'recordedAt' => $entry->recorded_at?->toIso8601String(),
        'recordedAtLabel' => $entry->recorded_at?->format('d M Y, H:i'),
        'patient' => [
            'id' => $patient?->id,
            'name' => $patient?->name ?: 'Unknown patient',
            'urlKey' => $patient?->url_key,
        ],
        'author' => [
            'id' => $author?->id,
            'name' => format_care_journal_author_name($author),
        ],
    ];
}

function map_patient_vital(PatientVital $vital): array
{
    $recorder = $vital->recordedBy;

    return [
        'id' => $vital->id,
        'heartRate' => $vital->heart_rate,
        'bpSystolic' => $vital->bp_systolic,
        'bpDiastolic' => $vital->bp_diastolic,
        'spo2' => $vital->spo2,
        'temperatureCelsius' => $vital->temperature_celsius !== null ? (float) $vital->temperature_celsius : null,
        'bloodGlucoseMmol' => $vital->blood_glucose_mmol !== null ? (float) $vital->blood_glucose_mmol : null,
        'weightKg' => $vital->weight_kg !== null ? (float) $vital->weight_kg : null,
        'painScore' => $vital->pain_score,
        'otherObservation' => $vital->other_observation,
        'thresholdAlerts' => evaluate_vital_threshold_alerts($vital),
        'recordedAt' => $vital->recorded_at?->toIso8601String(),
        'recordedAtLabel' => $vital->recorded_at?->format('d M Y, H:i'),
        'recordedBy' => [
            'id' => $recorder?->id,
            'name' => format_care_journal_author_name($recorder),
        ],
    ];
}

function care_journal_entries_query(Request $request)
{
    $user = $request->user();
    $filter = (string) $request->query('filter', 'all');

    $query = CareJournalEntry::query()
        ->with(['patient:id,name,url_key', 'author:id,name,first_name,surname'])
        ->orderByDesc('recorded_at')
        ->orderByDesc('id');

    if ($filter === 'mine') {
        $query->where('author_user_id', $user->id);
    }

    return $query;
}
} // care journal helpers

Route::get('/dashboard/journal', function (Request $request) {
    $filter = (string) $request->query('filter', 'all');
    if (!in_array($filter, ['all', 'mine'], true)) {
        $filter = 'all';
    }

    $entries = care_journal_entries_query($request)
        ->limit(200)
        ->get()
        ->map(fn (CareJournalEntry $entry) => map_care_journal_entry($entry))
        ->values();

    $patients = Patient::query()
        ->orderBy('name')
        ->get(['id', 'name', 'url_key'])
        ->map(fn ($patient) => [
            'id' => $patient->id,
            'name' => $patient->name,
            'urlKey' => $patient->url_key,
        ])
        ->values();

    return Inertia::render('Journal', [
        'entries' => $entries,
        'patients' => $patients,
        'filter' => $filter,
    ]);
})->middleware(['auth', 'verified'])->name('journal');

Route::post('/dashboard/journal', function (Request $request) {
    $filter = (string) $request->input('filter', 'all');
    if (!in_array($filter, ['all', 'mine'], true)) {
        $filter = 'all';
    }

    $validated = $request->validate([
        'patient_id' => ['required', 'integer', 'exists:patients,id'],
        'body' => ['required', 'string', 'min:3', 'max:10000'],
    ]);

    $entry = CareJournalEntry::query()->create([
        'patient_id' => $validated['patient_id'],
        'author_user_id' => $request->user()->id,
        'body' => trim($validated['body']),
        'recorded_at' => now(),
    ]);

    $patient = Patient::query()->find($validated['patient_id']);
    AuditTrail::record(
        'created',
        'Recorded daily care note for '.($patient?->name ?? 'patient'),
        'care_journal',
        (string) $entry->id,
        $patient?->name,
        null,
        ['patient_url_key' => $patient?->url_key],
        $request,
    );

    return redirect()
        ->route('journal', ['filter' => $filter])
        ->with('success', 'Daily care note recorded.');
})->middleware(['auth', 'verified'])->name('journal.store');

Route::get('/schedules', function () {
    $patients = Patient::query()
        ->orderBy('name')
        ->get(['id', 'name', 'url_key', 'reference'])
        ->map(fn ($patient) => [
            'id' => $patient->id,
            'name' => $patient->name,
            'urlKey' => $patient->url_key,
            'reference' => $patient->reference,
        ])
        ->values();

    $staff = User::query()
        ->orderBy('name')
        ->get(['id', 'name', 'first_name', 'surname', 'primary_role'])
        ->filter(fn ($user) => user_is_care_worker($user))
        ->map(function ($user) {
            $fullName = trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))));

            return [
                'id' => $user->id,
                'name' => $fullName !== '' ? $fullName : 'Unnamed staff',
                'role' => $user->primary_role ? Str::of($user->primary_role)->replace('_', ' ')->title()->toString() : 'Staff',
            ];
        })
        ->values();

    $entries = PatientSchedule::query()
        ->with(['patient:id,name,url_key,reference', 'assignedUser:id,name,first_name,surname'])
        ->orderBy('start_at')
        ->limit(200)
        ->get()
        ->map(function ($entry) {
            $staffName = trim((string) ($entry->assignedUser?->name ?? ''));
            if ($staffName === '') {
                $staffName = trim((string) (($entry->assignedUser?->first_name ?? '').' '.($entry->assignedUser?->surname ?? '')));
            }
            if ($staffName === '') {
                $staffName = 'Unassigned';
            }

            $spansOvernight = $entry->start_at && $entry->end_at
                && ! $entry->end_at->isSameDay($entry->start_at);

            return [
                'id' => $entry->id,
                'patientName' => $entry->patient?->name ?? 'Unknown patient',
                'patientUrlKey' => $entry->patient?->url_key,
                'patientReference' => $entry->patient?->reference,
                'staffName' => $staffName,
                'assignedUserId' => $entry->assigned_user_id,
                'startAt' => optional($entry->start_at)->toIso8601String(),
                'endAt' => optional($entry->end_at)->toIso8601String(),
                'spansOvernight' => $spansOvernight,
                'purpose' => $entry->purpose,
                'notes' => $entry->notes,
                'completionStatus' => $entry->status,
            ];
        })
        ->values();

    return Inertia::render('Schedules', [
        'patients' => $patients,
        'staff' => $staff,
        'entries' => $entries,
    ]);
})->middleware(['auth', 'verified'])->name('schedules');

Route::post('/schedules', function () {
    $payload = request()->validate([
        'patient_url_key' => ['required', 'string', 'exists:patients,url_key'],
        'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
        'visit_date' => ['required', 'date'],
        'start_time' => ['required', 'date_format:H:i'],
        'end_time' => ['required', 'date_format:H:i'],
        'purpose' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $patient = Patient::query()->where('url_key', $payload['patient_url_key'])->firstOrFail();
    $window = resolve_schedule_window($payload['visit_date'], $payload['start_time'], $payload['end_time']);

    $assignedUser = resolve_care_worker_user_or_fail((int) $payload['assigned_user_id']);

    $schedule = PatientSchedule::query()->create([
        'patient_id' => $patient->id,
        'assigned_user_id' => $assignedUser->id,
        'start_at' => $window['start_at'],
        'end_at' => $window['end_at'],
        'purpose' => $payload['purpose'] ?? null,
        'notes' => $payload['notes'] ?? null,
        'created_by_user_id' => request()->user()?->id,
    ]);

    seed_schedule_visit_tasks($schedule);

    AuditTrail::record(
        'created',
        'Scheduled visit for '.$patient->name,
        'schedule',
        (string) $schedule->id,
        $patient->name,
        [
            'visit_date' => $payload['visit_date'],
            'start_time' => $payload['start_time'],
            'end_time' => $payload['end_time'],
            'assigned_user_id' => $assignedUser->id,
        ],
        ['patient_url_key' => $patient->url_key],
    );

    return redirect()->route('schedules')->with('success', 'Visit scheduled successfully.');
})->middleware(['auth', 'verified', 'throttle:5,1'])->name('schedules.store');

// Backward-compatibility safety net:
// Some stale clients can still emit PATCH /schedules (without schedule id),
// which previously caused noisy 405 errors. Swallow gracefully.
Route::patch('/schedules', function () {
    return response()->json([
        'ok' => false,
        'legacy' => true,
        'message' => 'Legacy schedules PATCH ignored.',
    ], 200);
})->middleware(['auth', 'verified'])->name('schedules.patch-legacy');

Route::patch('/schedules/{schedule}', function (PatientSchedule $schedule) {
    $payload = request()->validate([
        'patient_url_key' => ['required', 'string', 'exists:patients,url_key'],
        'visit_date' => ['required', 'date'],
        'start_time' => ['required', 'date_format:H:i'],
        'end_time' => ['required', 'date_format:H:i'],
        'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
    ]);

    $patient = Patient::query()->where('url_key', $payload['patient_url_key'])->firstOrFail();
    $window = resolve_schedule_window($payload['visit_date'], $payload['start_time'], $payload['end_time']);

    $updates = [
        'patient_id' => $patient->id,
        'start_at' => $window['start_at'],
        'end_at' => $window['end_at'],
    ];

    // Future-safe: if a reassign action reuses this endpoint, enforce care-worker only.
    if (array_key_exists('assigned_user_id', $payload) && $payload['assigned_user_id'] !== null) {
        $updates['assigned_user_id'] = resolve_care_worker_user_or_fail((int) $payload['assigned_user_id'])->id;
    }

    $previous = [
        'start_at' => optional($schedule->start_at)->toIso8601String(),
        'end_at' => optional($schedule->end_at)->toIso8601String(),
        'assigned_user_id' => $schedule->assigned_user_id,
    ];

    $schedule->update($updates);

    AuditTrail::record(
        'updated',
        'Rescheduled visit for '.$patient->name,
        'schedule',
        (string) $schedule->id,
        $patient->name,
        [
            'before' => $previous,
            'after' => [
                'start_at' => $window['start_at']->toIso8601String(),
                'end_at' => $window['end_at']->toIso8601String(),
                'assigned_user_id' => $updates['assigned_user_id'] ?? $schedule->assigned_user_id,
            ],
        ],
        ['patient_url_key' => $patient->url_key],
    );

    return redirect()->route('schedules')->with('success', 'Schedule updated successfully.');
})->middleware(['auth', 'verified'])->name('schedules.reschedule');

Route::patch('/schedules/{schedule}/complete', function (PatientSchedule $schedule) {
    $payload = request()->validate([
        'notes' => ['nullable', 'string', 'max:2000'],
        'status' => ['nullable', 'string', 'in:completed,missed'],
    ]);

    $status = $payload['status'] ?? 'completed';
    $notes = $payload['notes'] ?: ($status === 'missed' ? 'Shift missed — carer did not attend' : 'Shift completed');

    $schedule->update([
        'notes' => $notes,
        'status' => $status,
    ]);

    $patientName = $schedule->patient?->name ?? 'Unknown';
    $description = $status === 'missed'
        ? "Marked visit as missed for {$patientName}"
        : "Marked visit as completed for {$patientName}";

    AuditTrail::record(
        'updated',
        $description,
        'schedule',
        (string) $schedule->id,
        $patientName,
    );

    $successMessage = $status === 'missed' ? 'Shift marked as missed.' : 'Visit marked as completed.';
    return redirect()->back()->with('success', $successMessage);
})->middleware(['auth', 'verified'])->name('schedules.complete');

Route::get('/reports', function () {
    abort_unless(AuditTrail::canViewReports(request()->user()), 403, 'You do not have permission to view audit reports.');

    $subjectType = request()->query('subject_type');
    if ($subjectType === 'all' || $subjectType === '') {
        $subjectType = null;
    }

    return Inertia::render('ReportsAudit', [
        'events' => AuditTrail::fetchAuditReportsForUi($subjectType),
        'filters' => [
            'subject_type' => request()->query('subject_type', 'all'),
        ],
        'subjectTypes' => [
            ['value' => 'all', 'label' => 'All areas'],
            ['value' => 'patient', 'label' => 'Patients'],
            ['value' => 'employee', 'label' => 'Staff'],
            ['value' => 'schedule', 'label' => 'Schedules'],
            ['value' => 'care_journal', 'label' => 'Care journal'],
            ['value' => 'care_plan', 'label' => 'Care plans'],
            ['value' => 'medication', 'label' => 'eMAR'],
            ['value' => 'document', 'label' => 'Documents'],
            ['value' => 'vital', 'label' => 'Observations'],
            ['value' => 'incident', 'label' => 'Incidents'],
            ['value' => 'form_snapshot', 'label' => 'Draft forms'],
        ],
    ]);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports');

Route::get('/reports/gdpr', function () {
    abort_unless(AuditTrail::canManagePrivacyRequests(request()->user()), 403);

    $requests = PrivacyRequest::query()
        ->with(['patient:id,name,url_key', 'requestedBy:id,name,first_name,surname', 'handledBy:id,name,first_name,surname', 'erasureJob'])
        ->orderByDesc('created_at')
        ->limit(200)
        ->get()
        ->map(fn (PrivacyRequest $row) => map_privacy_request($row))
        ->values();

    $patients = Patient::query()
        ->orderBy('name')
        ->get(['id', 'name', 'url_key'])
        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'urlKey' => $p->url_key])
        ->values();

    $retentionSchedules = Schema::hasTable('data_retention_schedules')
        ? DataRetentionSchedule::query()->orderBy('data_category')->get()
            ->map(fn (DataRetentionSchedule $row) => map_data_retention_schedule($row))
            ->values()
        : collect();

    $privacyNotices = Schema::hasTable('privacy_notices')
        ? PrivacyNotice::query()->orderByDesc('published_at')->orderByDesc('id')->limit(50)->get()
            ->map(fn (PrivacyNotice $row) => map_privacy_notice($row))
            ->values()
        : collect();

    return Inertia::render('ReportsGdpr', [
        'requests' => $requests,
        'patients' => $patients,
        'statusOptions' => PrivacyRequest::STATUSES,
        'typeOptions' => collect(PrivacyRequest::TYPES)
            ->map(fn (string $type) => ['value' => $type, 'label' => privacy_request_type_label($type)])
            ->values(),
        'retentionSchedules' => $retentionSchedules,
        'privacyNotices' => $privacyNotices,
    ]);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr');

Route::post('/reports/gdpr', function (Request $request) {
    abort_unless(AuditTrail::canManagePrivacyRequests($request->user()), 403);

    $validated = $request->validate([
        'request_type' => ['required', 'string', 'in:'.implode(',', PrivacyRequest::TYPES)],
        'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
        'subject_name' => ['nullable', 'string', 'max:255'],
        'subject_email' => ['nullable', 'email', 'max:255'],
        'request_details' => ['required', 'string', 'min:10', 'max:10000'],
        'discovered_at' => ['nullable', 'date'],
        'ico_notification_required' => ['nullable', 'boolean'],
        'individuals_affected_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        'breach_categories' => ['nullable', 'string', 'max:255'],
    ]);

    $patient = isset($validated['patient_id'])
        ? Patient::query()->find($validated['patient_id'])
        : null;

    if (!$patient && trim((string) ($validated['subject_name'] ?? '')) === '' && $validated['request_type'] !== PrivacyRequest::TYPE_DATA_BREACH) {
        throw ValidationException::withMessages([
            'subject_name' => 'Link a patient or enter the data subject name.',
        ]);
    }

    $discoveredAt = isset($validated['discovered_at'])
        ? Carbon::parse((string) $validated['discovered_at'])
        : null;

    if ($validated['request_type'] === PrivacyRequest::TYPE_DATA_BREACH) {
        $discoveredAt = $discoveredAt ?? now();
        $dueAt = $discoveredAt->copy()->addHours(72)->toDateString();
        $icoRequired = array_key_exists('ico_notification_required', $validated)
            ? (bool) $validated['ico_notification_required']
            : true;
    } else {
        $dueAt = now()->addDays(30)->toDateString();
        $icoRequired = false;
        $discoveredAt = null;
    }

    $privacyRequest = PrivacyRequest::query()->create([
        'request_type' => $validated['request_type'],
        'status' => 'pending',
        'patient_id' => $patient?->id,
        'subject_name' => $patient?->name ?? trim((string) ($validated['subject_name'] ?? '')) ?: 'Data breach incident',
        'subject_email' => trim((string) ($validated['subject_email'] ?? '')) ?: null,
        'request_details' => trim($validated['request_details']),
        'requested_by_user_id' => $request->user()->id,
        'due_at' => $dueAt,
        'discovered_at' => $discoveredAt,
        'ico_notification_required' => $icoRequired,
        'individuals_affected_count' => $validated['individuals_affected_count'] ?? null,
        'breach_categories' => trim((string) ($validated['breach_categories'] ?? '')) ?: null,
    ]);

    $typeLabel = match ($validated['request_type']) {
        PrivacyRequest::TYPE_ERASURE => 'Erasure',
        PrivacyRequest::TYPE_DATA_BREACH => 'Data breach',
        default => 'SAR',
    };
    AuditTrail::record(
        'created',
        "Opened {$typeLabel} privacy request for ".($privacyRequest->subject_name ?? 'data subject'),
        'privacy_request',
        (string) $privacyRequest->id,
        $privacyRequest->subject_name,
        ['request_type' => $validated['request_type'], 'due_at' => $privacyRequest->due_at?->toDateString()],
        ['patient_url_key' => $patient?->url_key],
        $request,
    );

    return redirect()->route('reports.gdpr')->with('success', 'Privacy request logged.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.store');

Route::patch('/reports/gdpr/{privacyRequest}', function (PrivacyRequest $privacyRequest, Request $request) {
    abort_unless(AuditTrail::canManagePrivacyRequests($request->user()), 403);

    $validated = $request->validate([
        'status' => ['required', 'string', 'in:'.implode(',', PrivacyRequest::STATUSES)],
        'outcome_notes' => ['nullable', 'string', 'max:10000'],
        'ico_notified_at' => ['nullable', 'date'],
        'ico_notification_required' => ['nullable', 'boolean'],
        'individuals_affected_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        'breach_categories' => ['nullable', 'string', 'max:255'],
    ]);

    $previousStatus = $privacyRequest->status;
    $updates = [
        'status' => $validated['status'],
        'outcome_notes' => trim((string) ($validated['outcome_notes'] ?? '')) ?: $privacyRequest->outcome_notes,
        'handled_by_user_id' => $request->user()->id,
        'completed_at' => in_array($validated['status'], ['completed', 'rejected'], true) ? now() : null,
    ];

    if ($privacyRequest->request_type === PrivacyRequest::TYPE_DATA_BREACH) {
        if (array_key_exists('ico_notification_required', $validated)) {
            $updates['ico_notification_required'] = (bool) $validated['ico_notification_required'];
        }
        if (array_key_exists('ico_notified_at', $validated) && $validated['ico_notified_at']) {
            $updates['ico_notified_at'] = Carbon::parse((string) $validated['ico_notified_at']);
        }
        if (array_key_exists('individuals_affected_count', $validated)) {
            $updates['individuals_affected_count'] = $validated['individuals_affected_count'];
        }
        if (array_key_exists('breach_categories', $validated)) {
            $updates['breach_categories'] = trim((string) $validated['breach_categories']) ?: null;
        }
    }

    $privacyRequest->update($updates);

    if (
        $validated['status'] === 'completed'
        && $privacyRequest->request_type === PrivacyRequest::TYPE_ERASURE
        && $privacyRequest->patient_id
    ) {
        $job = queue_privacy_erasure_job($privacyRequest->fresh());
        process_privacy_erasure_job($job);
    }

    AuditTrail::record(
        'updated',
        'Updated privacy request #'.$privacyRequest->id.' status to '.$validated['status'],
        'privacy_request',
        (string) $privacyRequest->id,
        $privacyRequest->subject_name,
        ['from' => $previousStatus, 'to' => $validated['status']],
        ['patient_url_key' => $privacyRequest->patient?->url_key],
        $request,
    );

    return redirect()->route('reports.gdpr')->with('success', 'Privacy request updated.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.update');

Route::get('/reports/gdpr/{privacyRequest}/sar-export', function (PrivacyRequest $privacyRequest) {
    abort_unless(AuditTrail::canManagePrivacyRequests(request()->user()), 403);

    if ($privacyRequest->request_type !== PrivacyRequest::TYPE_SUBJECT_ACCESS) {
        abort(404);
    }

    $patient = $privacyRequest->patient;
    if (!$patient) {
        return redirect()->route('reports.gdpr')->withErrors([
            'export' => 'Link a patient to this SAR before exporting.',
        ]);
    }

    $export = build_subject_access_export($patient);
    $filename = 'sar-'.$patient->url_key.'-'.now()->format('Y-m-d').'.json';

    AuditTrail::record(
        'exported',
        'Exported SAR data pack for '.$patient->name,
        'privacy_request',
        (string) $privacyRequest->id,
        $patient->name,
        null,
        ['patient_url_key' => $patient->url_key, 'filename' => $filename],
        request(),
    );

    return response()->json($export, 200, [
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.sar-export');

Route::get('/reports/gdpr/{privacyRequest}/sar-export.pdf', function (PrivacyRequest $privacyRequest) {
    abort_unless(AuditTrail::canManagePrivacyRequests(request()->user()), 403);

    if ($privacyRequest->request_type !== PrivacyRequest::TYPE_SUBJECT_ACCESS) {
        abort(404);
    }

    $patient = $privacyRequest->patient;
    if (!$patient) {
        return redirect()->route('reports.gdpr')->withErrors([
            'export' => 'Link a patient to this SAR before exporting.',
        ]);
    }

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
        'reports.sar-export-pdf',
        prepare_sar_pdf_view_data($patient, $privacyRequest)
    )->setPaper('a4', 'portrait');

    $filename = 'AlloCare-SAR-'.Str::slug($patient->name).'-'.now()->format('Y-m-d').'.pdf';

    AuditTrail::record(
        'exported',
        'Exported SAR PDF for '.$patient->name,
        'privacy_request',
        (string) $privacyRequest->id,
        $patient->name,
        null,
        ['patient_url_key' => $patient->url_key, 'filename' => $filename],
        request(),
    );

    return $pdf->download($filename);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.sar-export.pdf');

Route::post('/reports/gdpr/retention', function (Request $request) {
    abort_unless(AuditTrail::canManagePrivacyRequests($request->user()), 403);

    $validated = $request->validate([
        'data_category' => ['required', 'string', 'max:128'],
        'retention_period' => ['required', 'string', 'max:128'],
        'legal_basis' => ['nullable', 'string', 'max:255'],
        'review_cycle_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        'last_reviewed_at' => ['nullable', 'date'],
        'notes' => ['nullable', 'string', 'max:5000'],
    ]);

    DataRetentionSchedule::query()->create([
        'data_category' => trim($validated['data_category']),
        'retention_period' => trim($validated['retention_period']),
        'legal_basis' => trim((string) ($validated['legal_basis'] ?? '')) ?: null,
        'review_cycle_months' => $validated['review_cycle_months'] ?? 12,
        'last_reviewed_at' => $validated['last_reviewed_at'] ?? now()->toDateString(),
        'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        'updated_by_user_id' => $request->user()->id,
    ]);

    return redirect()->route('reports.gdpr')->with('success', 'Retention schedule added.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.retention.store');

Route::post('/reports/gdpr/privacy-notices', function (Request $request) {
    abort_unless(AuditTrail::canManagePrivacyRequests($request->user()), 403);

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'version' => ['required', 'string', 'max:32'],
        'summary' => ['nullable', 'string', 'max:2000'],
        'content' => ['required', 'string', 'min:20', 'max:50000'],
        'published_at' => ['nullable', 'date'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    if ($request->boolean('is_active', true)) {
        PrivacyNotice::query()->where('is_active', true)->update(['is_active' => false]);
    }

    PrivacyNotice::query()->create([
        'title' => trim($validated['title']),
        'version' => trim($validated['version']),
        'summary' => trim((string) ($validated['summary'] ?? '')) ?: null,
        'content' => trim($validated['content']),
        'published_at' => $validated['published_at'] ?? now()->toDateString(),
        'is_active' => $request->boolean('is_active', true),
        'published_by_user_id' => $request->user()->id,
    ]);

    return redirect()->route('reports.gdpr')->with('success', 'Privacy notice published.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.privacy-notices.store');

Route::post('/reports/gdpr/retention/{schedule}/reviewed', function (Request $request, DataRetentionSchedule $schedule) {
    abort_unless(AuditTrail::canManagePrivacyRequests($request->user()), 403);

    $schedule->update([
        'last_reviewed_at' => now()->toDateString(),
        'updated_by_user_id' => $request->user()->id,
    ]);

    return redirect()->route('reports.gdpr')->with('success', 'Retention schedule marked as reviewed.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.retention.reviewed');

Route::post('/reports/gdpr/erasure-jobs/{job}/retry', function (Request $request, PrivacyErasureJob $job) {
    abort_unless(AuditTrail::canManagePrivacyRequests($request->user()), 403);

    abort_unless($job->status === PrivacyErasureJob::STATUS_FAILED, 422);

    $job->update([
        'status' => PrivacyErasureJob::STATUS_PENDING,
        'scheduled_at' => now(),
        'error_message' => null,
    ]);

    process_privacy_erasure_job($job->fresh());

    return redirect()->route('reports.gdpr')->with('success', 'Erasure job reprocessed.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.gdpr.erasure-jobs.retry');

Route::get('/reports/staff-performance', function () {
    abort_unless(AuditTrail::canViewReports(request()->user()), 403);

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $schedules = PatientSchedule::query()
        ->whereBetween('start_at', [$from, $to])
        ->with('assignedUser:id,name,first_name,surname')
        ->get();

    $byStaff = $schedules
        ->groupBy(fn (PatientSchedule $s) => $s->assigned_user_id ?? 0)
        ->map(function ($group, $userId) {
            $user = $group->first()->assignedUser;
            $name = $user
                ? trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))))
                : 'Unassigned';
            $total = $group->count();
            $completed = $group->where('status', 'completed')->count();
            $missed = $group->where('status', 'missed')->count();
            $late = $group->sum(fn ($s) => (int) ($s->late_by_minutes ?? 0));
            $hours = $group->sum(fn ($s) => ($s->start_at && $s->end_at) ? $s->start_at->diffInMinutes($s->end_at) / 60 : 0);

            return [
                'staffId' => $userId ?: null,
                'staffName' => $name !== '' ? $name : 'Unassigned',
                'totalShifts' => $total,
                'completedShifts' => $completed,
                'missedShifts' => $missed,
                'completionRate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'lateMinutesTotal' => $late,
                'hoursAllocated' => round($hours, 1),
            ];
        })
        ->sortByDesc('totalShifts')
        ->values();

    return Inertia::render('ReportsStaffPerformance', [
        'byStaff' => $byStaff,
        'stats' => [
            'totalShifts' => $schedules->count(),
            'staffCount' => $byStaff->where('staffId', '!=', null)->count(),
            'avgCompletionRate' => $byStaff->count() > 0
                ? round($byStaff->avg('completionRate'), 1)
                : 0,
        ],
        'filters' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
    ]);
})->middleware(['auth', 'verified'])->name('reports.staff-performance');

Route::get('/reports/clinical-outcomes', function () {
    abort_unless(AuditTrail::canViewReports(request()->user()), 403);

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $vitals = PatientVital::query()->whereBetween('recorded_at', [$from, $to])->get();
    $fluid = PatientFluidRecord::query()->whereBetween('recorded_at', [$from, $to])->get();
    $bowel = PatientBowelRecord::query()->whereBetween('recorded_at', [$from, $to])->get();
    $wounds = PatientWoundAssessment::query()->whereBetween('recorded_at', [$from, $to])->get();

    $highPain = $vitals->where('pain_score', '>=', 7)->count()
        + $wounds->where('pain_score', '>=', 7)->count();
    $lowSpo2 = $vitals->where('spo2', '<', 94)->whereNotNull('spo2')->count();
    $woundEscalations = $wounds->where('escalation_required', true)->count();
    $fluidIntakeMl = (int) $fluid->sum('fluid_intake_ml');

    $weeklyVitals = $vitals->groupBy(fn ($v) => $v->recorded_at?->format('Y-W') ?? 'unknown')
        ->map(fn ($group, $week) => [
            'week' => $week,
            'count' => $group->count(),
            'avgHeartRate' => round($group->avg('heart_rate') ?? 0, 1),
            'avgSpo2' => round($group->avg('spo2') ?? 0, 1),
        ])
        ->values()
        ->take(12);

    return Inertia::render('ReportsClinicalOutcomes', [
        'stats' => [
            'vitalEntries' => $vitals->count(),
            'fluidEntries' => $fluid->count(),
            'bowelEntries' => $bowel->count(),
            'woundAssessments' => $wounds->count(),
            'highPainFlags' => $highPain,
            'lowSpo2Flags' => $lowSpo2,
            'woundEscalations' => $woundEscalations,
            'fluidIntakeMl' => $fluidIntakeMl,
        ],
        'weeklyVitals' => $weeklyVitals,
        'filters' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
    ]);
})->middleware(['auth', 'verified'])->name('reports.clinical-outcomes');

Route::get('/reports/export/audit/csv', function () {
    abort_unless(AuditTrail::canViewReports(request()->user()), 403, 'You do not have permission to view audit reports.');

    $subjectType = request()->query('subject_type');
    if ($subjectType === 'all' || $subjectType === '') {
        $subjectType = null;
    }

    $events = AuditTrail::fetchAuditReportsForUi($subjectType);
    $filename = 'Allocare-audit-report-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($events): void {
        $output = fopen('php://output', 'w');
        if (! $output) {
            return;
        }

        fputcsv($output, ['When', 'User', 'Subject', 'Description', 'Action', 'Path', 'IP']);
        foreach ($events as $event) {
            fputcsv($output, [
                $event['created_at'] ?? '',
                $event['user_name'] ?? ($event['user_id'] ? 'User #'.$event['user_id'] : 'System'),
                $event['subject_label'] ?? $event['subject_key'] ?? '-',
                $event['description'] ?? '',
                $event['action'] ?? '',
                $event['request_path'] ?? '',
                $event['ip_address'] ?? '',
            ]);
        }

        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.audit.export.csv');

Route::get('/reports/export/audit/pdf', function () {
    abort_unless(AuditTrail::canViewReports(request()->user()), 403, 'You do not have permission to view audit reports.');

    $subjectType = request()->query('subject_type');
    if ($subjectType === 'all' || $subjectType === '') {
        $subjectType = null;
    }

    $events = AuditTrail::fetchAuditReportsForUi($subjectType);
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.audit-pdf', [
        'events' => $events,
        'subjectType' => $subjectType ?? 'all',
    ])->setPaper('a4', 'landscape');

    return $pdf->download('Allocare-audit-report-'.now()->format('Ymd-His').'.pdf');
})->middleware(['auth', 'verified'])->name('reports.audit.export.pdf');

Route::get('/admin/activity-logs', function () {
    abort_unless(AuditTrail::canViewActivityLog(request()->user()), 403, 'You do not have permission to view activity logs.');

    $hasDatabase = Schema::hasTable('user_activity_logs');

    return Inertia::render('AdminActivityLogs', [
        'logs' => AuditTrail::fetchActivityLogsForUi(),
        'tableAvailable' => $hasDatabase || File::exists(storage_path('logs/audit-actions.log')),
        'logSource' => $hasDatabase ? 'database' : 'audit_file',
    ]);
})->middleware(['auth', 'verified'])->name('admin.activity-logs');

Route::get('/patients', function () {
    $patients = Patient::query()
        ->latest()
        ->get()
        ->map(fn ($patient) => [
            'urlKey' => $patient->url_key,
            'slug' => $patient->slug,
            'name' => $patient->name,
            'reference' => $patient->reference ?? '#UNASSIGNED',
            'photoUrl' => $patient->photo_path ? route('patients.photo', ['patientRecord' => $patient->id]) : null,
            'dob' => $patient->dob ?? 'Not provided',
            'allergies' => $patient->allergies ?? ['None'],
            'address' => $patient->address ?? 'Not provided',
            'phone' => $patient->phone ?? 'Not provided',
            'status' => $patient->status,
            'date' => optional($patient->created_at)->format('d M Y') ?: now()->format('d M Y'),
            'avatar' => $patient->avatar ?: 'bg-slate-300',
        ]);

    return Inertia::render('Patients', [
        'patients' => $patients,
    ]);
})->middleware(['auth', 'verified'])->name('patients');

Route::get('/patients/create', function () {
    return Inertia::render('PatientsCreate');
})->middleware(['auth', 'verified'])->name('patients.create');

Route::get('/patients/{patientRecord}/photo', function (Patient $patientRecord) {
    abort_unless($patientRecord->photo_path && Storage::disk('public')->exists($patientRecord->photo_path), 404);

    return Storage::disk('public')->response($patientRecord->photo_path);
})->middleware(['auth', 'verified'])->name('patients.photo');

Route::post('/patients', function () {
    $payload = request()->validate([
        'title' => ['required', 'string', 'max:20'],
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
        'gender' => ['required', 'string', 'max:50'],
        'primary_diagnosis' => ['nullable', 'string', 'max:500'],
        'severe_allergies' => ['nullable', 'string', 'max:500'],
        'allergy_details' => ['nullable', 'array'],
        'allergy_details.*.allergen' => ['required_with:allergy_details', 'string', 'max:255'],
        'allergy_details.*.reaction' => ['nullable', 'string', 'max:255'],
        'allergy_details.*.severity' => ['nullable', 'string', 'max:100'],
        'allergy_details.*.verified_at' => ['nullable', 'date'],
        'preferred_name' => ['nullable', 'string', 'max:255'],
        'gp_name' => ['nullable', 'string', 'max:255'],
        'gp_practice' => ['nullable', 'string', 'max:255'],
        'gp_phone' => ['nullable', 'string', 'max:50'],
        'primary_language' => ['nullable', 'string', 'max:100'],
        'interpreter_required' => ['nullable', 'boolean'],
        'capacity_status' => ['nullable', 'string', 'max:255'],
        'best_interest_decision' => ['nullable', 'string', 'max:2000'],
        'information_sharing_consent' => ['nullable', 'string', 'max:255'],
        'dols_lps_status' => ['nullable', 'string', 'max:255'],
        'dnacpr_status' => ['nullable', 'string', 'max:255'],
        'mobility_aids' => ['nullable', 'string', 'max:500'],
        'hoist_type' => ['nullable', 'string', 'max:255'],
        'sling_size' => ['nullable', 'string', 'max:100'],
        'equipment_notes' => ['nullable', 'string', 'max:2000'],
        'environmental_notes' => ['nullable', 'string', 'max:2000'],
        'social_worker_name' => ['nullable', 'string', 'max:255'],
        'social_worker_contact' => ['nullable', 'string', 'max:100'],
        'commissioner_name' => ['nullable', 'string', 'max:255'],
        'commissioner_contact' => ['nullable', 'string', 'max:100'],
        'emergency_contact_name' => ['nullable', 'string', 'max:255'],
        'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
        'rag_status' => ['required', 'string', 'in:green,amber,red'],
        'staffing_ratio' => ['required', 'string', 'max:50'],
        'address_line_1' => ['required', 'string', 'max:255'],
        'city' => ['required', 'string', 'max:255'],
        'postcode' => ['required', 'string', 'max:50'],
        'phone_number' => ['nullable', 'string', 'regex:/^07\d{9}$/'],
        'email_address' => ['required', 'email', 'max:255'],
        'next_of_kin' => ['required', 'string', 'max:255'],
        'next_of_kin_tel' => ['nullable', 'string', 'regex:/^07\d{9}$/'],
        'next_of_kin_email' => ['nullable', 'email', 'max:255'],
        'other_relevant_people' => ['nullable', 'string', 'max:1000'],
        'social_services_number' => ['nullable', 'string', 'max:100'],
        'weight_kg' => ['required', 'numeric', 'between:1,500'],
        'height_m' => ['required', 'numeric', 'between:0.3,3'],
        'start_date' => ['required', 'date'],
        'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
        'photo_base64' => ['nullable', 'string', 'max:7000000'],
        'photo_filename' => ['nullable', 'string', 'max:255'],
        'name' => ['required', 'string', 'max:255'],
        'nhs_number' => [
            'required',
            'string',
            'regex:/^\d{10}$/',
            'unique:patients,nhs_number',
        ],
        'dob' => ['required', 'string', 'max:50'],
        'allergies' => ['nullable', 'string', 'max:500'],
        'address' => ['required', 'string', 'max:500'],
        'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        'phone' => ['nullable', 'string', 'max:100'],
        'status' => ['required', 'string', 'in:GREEN,AMBER,RED'],
    ]);

    $name = trim($payload['name']);
    $slug = Str::slug($name);
    $urlKey = 'ac-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

    while (Patient::query()->where('url_key', $urlKey)->exists()) {
        $urlKey = 'ac-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    $geocoded = [
        'latitude' => !empty($payload['latitude']) ? (float) $payload['latitude'] : null,
        'longitude' => !empty($payload['longitude']) ? (float) $payload['longitude'] : null,
    ];
    if (!$geocoded['latitude'] && !empty($payload['address'])) {
        try {
            $geoResponse = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'AlloCare/1.0'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $payload['address'],
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'gb',
                ]);
            if ($geoResponse->ok() && !empty($geoResponse->json())) {
                $geocoded['latitude'] = (float) $geoResponse->json()[0]['lat'];
                $geocoded['longitude'] = (float) $geoResponse->json()[0]['lon'];
            }
        } catch (\Throwable $e) {
            // Geocoding is best-effort; proceed without coordinates
        }
    }

    $allergyDetails = normalize_patient_allergy_details(
        $payload['severe_allergies'] ?? ($payload['allergies'] ?? null),
        $payload['allergy_details'] ?? null,
    );
    $legacyAllergies = !empty($allergyDetails)
        ? collect($allergyDetails)->pluck('allergen')->all()
        : (!empty($payload['allergies'] ?? null) ? array_map('trim', explode(',', (string) $payload['allergies'])) : ['None']);

    $patient = Patient::query()->create([
        'url_key' => $urlKey,
        'slug' => $slug,
        'name' => $name,
        'preferred_name' => ($payload['preferred_name'] ?? null) ?: null,
        'reference' => '#'.strtoupper($urlKey),
        'nhs_number' => $payload['nhs_number'] ?: null,
        'gp_name' => ($payload['gp_name'] ?? null) ?: null,
        'gp_practice' => ($payload['gp_practice'] ?? null) ?: null,
        'gp_phone' => ($payload['gp_phone'] ?? null) ?: null,
        'primary_language' => ($payload['primary_language'] ?? null) ?: null,
        'interpreter_required' => (bool) ($payload['interpreter_required'] ?? false),
        'capacity_status' => ($payload['capacity_status'] ?? null) ?: null,
        'best_interest_decision' => ($payload['best_interest_decision'] ?? null) ?: null,
        'information_sharing_consent' => ($payload['information_sharing_consent'] ?? null) ?: null,
        'dols_lps_status' => ($payload['dols_lps_status'] ?? null) ?: null,
        'dnacpr_status' => ($payload['dnacpr_status'] ?? null) ?: null,
        'allergy_details' => !empty($allergyDetails) ? $allergyDetails : null,
        'mobility_aids' => ($payload['mobility_aids'] ?? null) ?: null,
        'hoist_type' => ($payload['hoist_type'] ?? null) ?: null,
        'sling_size' => ($payload['sling_size'] ?? null) ?: null,
        'equipment_notes' => ($payload['equipment_notes'] ?? null) ?: null,
        'environmental_notes' => ($payload['environmental_notes'] ?? null) ?: null,
        'social_worker_name' => ($payload['social_worker_name'] ?? null) ?: null,
        'social_worker_contact' => ($payload['social_worker_contact'] ?? null) ?: null,
        'commissioner_name' => ($payload['commissioner_name'] ?? null) ?: null,
        'commissioner_contact' => ($payload['commissioner_contact'] ?? null) ?: null,
        'emergency_contact_name' => ($payload['emergency_contact_name'] ?? null) ?: null,
        'emergency_contact_phone' => ($payload['emergency_contact_phone'] ?? null) ?: null,
        'primary_diagnosis' => ($payload['primary_diagnosis'] ?? null) ?: null,
        'photo_path' => (function () use ($payload) {
            if (request()->hasFile('photo')) {
                return request()->file('photo')->store('patient-photos', 'public');
            }
            if (!empty($payload['photo_base64'])) {
                $decoded = base64_decode((string) $payload['photo_base64'], true);
                if ($decoded !== false) {
                    $ext = pathinfo((string) ($payload['photo_filename'] ?? 'patient.jpg'), PATHINFO_EXTENSION) ?: 'jpg';
                    $filename = 'patient-photos/'.Str::uuid().'.'.Str::lower($ext);
                    Storage::disk('public')->put($filename, $decoded);

                    return $filename;
                }
            }

            return null;
        })(),
        'dob' => $payload['dob'] ?: null,
        'allergies' => $legacyAllergies,
        'address' => $payload['address'] ?: null,
        'latitude' => $geocoded['latitude'],
        'longitude' => $geocoded['longitude'],
        'phone' => $payload['phone'] ?: null,
        'status' => $payload['status'],
        'rag_status' => $payload['rag_status'],
        'staffing_ratio' => $payload['staffing_ratio'],
        'next_of_kin' => $payload['next_of_kin'],
        'next_of_kin_tel' => ($payload['next_of_kin_tel'] ?? null) ?: null,
        'next_of_kin_email' => ($payload['next_of_kin_email'] ?? null) ?: null,
        'other_relevant_people' => ($payload['other_relevant_people'] ?? null) ?: null,
        'social_services_number' => ($payload['social_services_number'] ?? null) ?: null,
        'avatar' => 'bg-slate-300',
    ]);

    AuditTrail::record(
        'created',
        'Registered patient '.$name,
        'patient',
        $urlKey,
        $name,
        [
            'nhs_number' => $payload['nhs_number'],
            'status' => $payload['status'],
            'rag_status' => $payload['rag_status'],
            'address' => $payload['address'],
            'latitude' => $geocoded['latitude'],
            'longitude' => $geocoded['longitude'],
        ],
    );

    \Illuminate\Support\Facades\Log::info('Patient registered', [
        'patient_id' => $patient->id,
        'url_key' => $urlKey,
        'name' => $name,
        'address' => $payload['address'],
        'latitude' => $geocoded['latitude'],
        'longitude' => $geocoded['longitude'],
    ]);

    return redirect()->route('patients')->with('success', 'Patient created successfully.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager'])->name('patients.store');

Route::get('/patients/{patient}', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $shiftSnapshot = FormSnapshot::query()->where('form_key', "shift-checkin:{$patient}")->first();
    $latestVitals = PatientVital::query()
        ->where('patient_id', $record->id)
        ->latest('recorded_at')
        ->latest('id')
        ->first();
    $activeAlerts = build_patient_profile_alert_messages($record);
    $nextVisit = PatientSchedule::query()
        ->with('assignedUser:id,name,first_name,surname')
        ->where('patient_id', $record->id)
        ->where('start_at', '>=', now())
        ->orderBy('start_at')
        ->first();

    $medicationStatus = null;
    $activeMedications = PatientMedication::query()
        ->where('patient_id', $record->id)
        ->where('active', true)
        ->orderBy('scheduled_time')
        ->get();
    $recentAdministrations = MedicationAdministration::query()
        ->where('patient_id', $record->id)
        ->where('created_at', '>=', now()->subDays(7))
        ->whereIn('status', ['given', 'due', 'refused'])
        ->get();
    if ($activeMedications->isNotEmpty()) {
        $givenCount = $recentAdministrations->where('status', 'given')->count();
        $totalCount = max(1, $recentAdministrations->count());
        $compliance = (int) round(($givenCount / $totalCount) * 100);

        $nowTime = now()->format('H:i:s');
        $nextDueMedication = $activeMedications
            ->first(fn ($medication) => is_string($medication->scheduled_time) && $medication->scheduled_time >= $nowTime)
            ?: $activeMedications->first();

        $nextDoseDue = '--:--';
        if (!empty($nextDueMedication?->scheduled_time)) {
            $nextDoseDue = Carbon::createFromFormat('H:i:s', (string) $nextDueMedication->scheduled_time)->format('H:i');
        }

        $medicationStatus = [
            'nextDoseDue' => $nextDoseDue,
            'description' => trim(((string) ($nextDueMedication?->name ?? 'No due medication')).' '.((string) ($nextDueMedication?->dose ?? ''))),
            'compliancePercent' => $compliance,
        ];
    }

    $sessionStartedAtRaw = data_get($shiftSnapshot?->data, 'sessionStartedAt');
    if (is_string($sessionStartedAtRaw) && $sessionStartedAtRaw !== '') {
        try {
            $sessionStartedAt = Carbon::parse($sessionStartedAtRaw);
            $scheduledStart = $sessionStartedAt->copy()->setTime(8, 0, 0);

            if ($sessionStartedAt->greaterThan($scheduledStart)) {
                $lateMinutes = $scheduledStart->diffInMinutes($sessionStartedAt);
                $activeAlerts[] = "Last visit started {$lateMinutes} minute".($lateMinutes === 1 ? '' : 's').' late.';
            }
        } catch (\Throwable) {
            // Ignore malformed snapshot dates so profile rendering remains stable.
        }
    }

    $recentJournalEntries = Schema::hasTable('care_journal_entries')
        ? CareJournalEntry::query()
            ->where('patient_id', $record->id)
            ->with('author:id,name,first_name,surname')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (CareJournalEntry $entry) => map_care_journal_entry($entry))
            ->values()
        : collect();

    $authUser = request()->user();
    $canEditProfile = user_has_primary_role($authUser, ['super_admin', 'admin', 'care_manager']);

    return Inertia::render('PatientRecord', [
        'patientSlug' => $patient,
        'patient' => map_patient_profile_payload($record),
        'canEditProfile' => $canEditProfile,
        'recentJournalEntries' => $recentJournalEntries,
        'latestVitals' => $latestVitals ? [
            'heartRate' => $latestVitals->heart_rate,
            'bpSystolic' => $latestVitals->bp_systolic,
            'spo2' => $latestVitals->spo2,
            'recordedAt' => optional($latestVitals->recorded_at ?? $latestVitals->created_at)->toIso8601String(),
        ] : null,
        'activeAlerts' => $activeAlerts,
        'medicationStatus' => $medicationStatus,
        'nextVisit' => $nextVisit ? [
            'staffName' => (trim((string) ($nextVisit->assignedUser?->name ?? '')) !== ''
                ? trim((string) ($nextVisit->assignedUser?->name ?? ''))
                : trim((string) (($nextVisit->assignedUser?->first_name ?? '').' '.($nextVisit->assignedUser?->surname ?? ''))))
                ?: 'Assigned staff',
            'startAt' => optional($nextVisit->start_at)->toIso8601String(),
            'endAt' => optional($nextVisit->end_at)->toIso8601String(),
            'purpose' => $nextVisit->purpose,
        ] : null,
    ]);
})->middleware(['auth', 'verified'])->name('patients.show');

Route::patch('/patients/{patient}/profile', function (Request $request, string $patient) {
    abort_unless(user_has_primary_role($request->user(), ['super_admin', 'admin', 'care_manager']), 403);

    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    $validated = $request->validate([
        'preferred_name' => ['nullable', 'string', 'max:255'],
        'gp_name' => ['nullable', 'string', 'max:255'],
        'gp_practice' => ['nullable', 'string', 'max:255'],
        'gp_phone' => ['nullable', 'string', 'max:50'],
        'primary_language' => ['nullable', 'string', 'max:100'],
        'interpreter_required' => ['nullable', 'boolean'],
        'capacity_status' => ['nullable', 'string', 'max:255'],
        'best_interest_decision' => ['nullable', 'string', 'max:2000'],
        'information_sharing_consent' => ['nullable', 'string', 'max:255'],
        'dols_lps_status' => ['nullable', 'string', 'max:255'],
        'dnacpr_status' => ['nullable', 'string', 'max:255'],
        'allergy_details' => ['nullable', 'array'],
        'allergy_details.*.allergen' => ['required_with:allergy_details', 'string', 'max:255'],
        'allergy_details.*.reaction' => ['nullable', 'string', 'max:255'],
        'allergy_details.*.severity' => ['nullable', 'string', 'max:100'],
        'allergy_details.*.verified_at' => ['nullable', 'date'],
        'mobility_aids' => ['nullable', 'string', 'max:500'],
        'hoist_type' => ['nullable', 'string', 'max:255'],
        'sling_size' => ['nullable', 'string', 'max:100'],
        'equipment_notes' => ['nullable', 'string', 'max:2000'],
        'environmental_notes' => ['nullable', 'string', 'max:2000'],
        'social_worker_name' => ['nullable', 'string', 'max:255'],
        'social_worker_contact' => ['nullable', 'string', 'max:100'],
        'commissioner_name' => ['nullable', 'string', 'max:255'],
        'commissioner_contact' => ['nullable', 'string', 'max:100'],
        'emergency_contact_name' => ['nullable', 'string', 'max:255'],
        'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
        'primary_diagnosis' => ['nullable', 'string', 'max:500'],
        'staffing_ratio' => ['nullable', 'string', 'max:50'],
        'next_of_kin' => ['nullable', 'string', 'max:255'],
        'next_of_kin_tel' => ['nullable', 'string', 'max:100'],
        'next_of_kin_email' => ['nullable', 'email', 'max:255'],
        'other_relevant_people' => ['nullable', 'string', 'max:1000'],
        'social_services_number' => ['nullable', 'string', 'max:100'],
    ]);

    $allergyDetails = normalize_patient_allergy_details(null, $validated['allergy_details'] ?? []);

    $record->update([
        'preferred_name' => $validated['preferred_name'] ?? null,
        'gp_name' => $validated['gp_name'] ?? null,
        'gp_practice' => $validated['gp_practice'] ?? null,
        'gp_phone' => $validated['gp_phone'] ?? null,
        'primary_language' => $validated['primary_language'] ?? null,
        'interpreter_required' => (bool) ($validated['interpreter_required'] ?? false),
        'capacity_status' => $validated['capacity_status'] ?? null,
        'best_interest_decision' => $validated['best_interest_decision'] ?? null,
        'information_sharing_consent' => $validated['information_sharing_consent'] ?? null,
        'dols_lps_status' => $validated['dols_lps_status'] ?? null,
        'dnacpr_status' => $validated['dnacpr_status'] ?? null,
        'allergy_details' => !empty($allergyDetails) ? $allergyDetails : null,
        'allergies' => !empty($allergyDetails) ? collect($allergyDetails)->pluck('allergen')->all() : ['None'],
        'mobility_aids' => $validated['mobility_aids'] ?? null,
        'hoist_type' => $validated['hoist_type'] ?? null,
        'sling_size' => $validated['sling_size'] ?? null,
        'equipment_notes' => $validated['equipment_notes'] ?? null,
        'environmental_notes' => $validated['environmental_notes'] ?? null,
        'social_worker_name' => $validated['social_worker_name'] ?? null,
        'social_worker_contact' => $validated['social_worker_contact'] ?? null,
        'commissioner_name' => $validated['commissioner_name'] ?? null,
        'commissioner_contact' => $validated['commissioner_contact'] ?? null,
        'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
        'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
        'primary_diagnosis' => $validated['primary_diagnosis'] ?? null,
        'staffing_ratio' => $validated['staffing_ratio'] ?? $record->staffing_ratio,
        'next_of_kin' => $validated['next_of_kin'] ?? $record->next_of_kin,
        'next_of_kin_tel' => $validated['next_of_kin_tel'] ?? $record->next_of_kin_tel,
        'next_of_kin_email' => $validated['next_of_kin_email'] ?? $record->next_of_kin_email,
        'other_relevant_people' => $validated['other_relevant_people'] ?? $record->other_relevant_people,
        'social_services_number' => $validated['social_services_number'] ?? $record->social_services_number,
    ]);

    AuditTrail::record(
        'updated',
        'Updated clinical profile for '.$record->name,
        'patient',
        $patient,
        $record->name,
        null,
        ['patient_url_key' => $patient],
    );

    return redirect()->back()->with('success', 'Patient profile updated successfully.');
})->middleware(['auth', 'verified'])->name('patients.profile.update');

Route::patch('/patients/{patient}/rag-status', function (Request $request, string $patient) {
    $authUser = $request->user();
    if ($authUser->primary_role !== 'super_admin') {
        abort(403, 'Only Super Admins can update the RAG status.');
    }

    $validated = $request->validate([
        'rag_status' => 'required|in:GREEN,AMBER,RED',
    ]);

    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $oldRag = $record->rag_status;
    $record->update(['rag_status' => $validated['rag_status']]);

    AuditTrail::record(
        'patient_rag_updated',
        "RAG status updated for {$record->name}: {$oldRag} → {$validated['rag_status']}",
        'patient',
        $record->url_key,
        $record->name,
        ['old_rag' => $oldRag, 'new_rag' => $validated['rag_status']]
    );

    return redirect()->back();
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager'])->name('patients.rag-status');

Route::get('/patients/{patient}/observations', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    $latestVitals = PatientVital::query()
        ->where('patient_id', $record->id)
        ->orderByDesc('recorded_at')
        ->orderByDesc('id')
        ->first();

    $observations = PatientVital::query()
        ->where('patient_id', $record->id)
        ->with(['recordedBy:id,name,first_name,surname'])
        ->orderByDesc('recorded_at')
        ->orderByDesc('id')
        ->limit(200)
        ->get()
        ->map(fn (PatientVital $vital) => map_patient_vital($vital))
        ->values();

    $fluidRecords = Schema::hasTable('patient_fluid_records')
        ? PatientFluidRecord::query()
            ->where('patient_id', $record->id)
            ->with(['recordedBy:id,name,first_name,surname'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (PatientFluidRecord $row) => map_patient_fluid_record($row))
            ->values()
        : collect();

    $bowelRecords = Schema::hasTable('patient_bowel_records')
        ? PatientBowelRecord::query()
            ->where('patient_id', $record->id)
            ->with(['recordedBy:id,name,first_name,surname'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (PatientBowelRecord $row) => map_patient_bowel_record($row))
            ->values()
        : collect();

    return Inertia::render('PatientObservations', [
        'patientSlug' => $patient,
        'patient' => [
            'name' => $record->name,
            'ragStatus' => $record->rag_status,
        ],
        'observations' => $observations,
        'latestVitals' => $latestVitals ? map_patient_vital($latestVitals) : null,
        'chartData' => build_patient_observation_chart_series($record),
        'fluidRecords' => $fluidRecords,
        'fluidBalanceSummary' => Schema::hasTable('patient_fluid_records')
            ? build_patient_fluid_balance_summary($record)
            : [],
        'bowelRecords' => $bowelRecords,
        'bristolOptions' => collect(PatientBowelRecord::BRISTOL_LABELS)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values(),
    ]);
})->middleware(['auth', 'verified'])->name('patients.observations');

Route::get('/patients/{patient}/handovers', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $scheduleId = request()->query('schedule_id');
    $scheduleId = $scheduleId !== null && $scheduleId !== '' ? (int) $scheduleId : null;

    $handovers = PatientHandover::query()
        ->where('patient_id', $record->id)
        ->with(['author:id,name,first_name,surname', 'schedule:id,start_at,end_at'])
        ->orderByDesc('recorded_at')
        ->orderByDesc('id')
        ->limit(100)
        ->get()
        ->map(fn (PatientHandover $handover) => map_patient_handover($handover))
        ->values();

    return Inertia::render('PatientHandovers', [
        'patientSlug' => $patient,
        'patient' => [
            'name' => $record->name,
            'ragStatus' => $record->rag_status,
        ],
        'handovers' => $handovers,
        'prefill' => [
            'scheduleId' => $scheduleId,
            'shiftDate' => now()->toDateString(),
            'shiftType' => (int) now()->format('G') >= 18 || (int) now()->format('G') < 7 ? 'night' : 'day',
        ],
    ]);
})->middleware(['auth', 'verified'])->name('patients.handovers');

Route::post('/patients/{patient}/handovers', function (string $patient, Request $request) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $validated = validate_patient_handover_payload($request->all());

    $scheduleId = isset($validated['schedule_id']) ? (int) $validated['schedule_id'] : null;
    if ($scheduleId) {
        $schedule = PatientSchedule::query()
            ->where('id', $scheduleId)
            ->where('patient_id', $record->id)
            ->first();
        if (!$schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => 'The selected visit is not valid for this patient.',
            ]);
        }
    } else {
        $schedule = null;
    }

    $handover = PatientHandover::query()->create([
        'patient_id' => $record->id,
        'patient_schedule_id' => $schedule?->id,
        'shift_type' => $validated['shift_type'],
        'shift_date' => $validated['shift_date'],
        'author_user_id' => $request->user()->id,
        'presentation' => $validated['shift_type'] === 'day' ? $validated['presentation'] : null,
        'care_delivered' => $validated['shift_type'] === 'day' ? $validated['care_delivered'] : null,
        'medication_summary' => $validated['shift_type'] === 'day' ? $validated['medication_summary'] : null,
        'risks_changes' => $validated['shift_type'] === 'day' ? $validated['risks_changes'] : null,
        'handover_notes' => $validated['shift_type'] === 'day' ? $validated['handover_notes'] : null,
        'sleep_summary' => $validated['shift_type'] === 'night' ? $validated['sleep_summary'] : null,
        'disturbances' => $validated['shift_type'] === 'night' ? $validated['disturbances'] : null,
        'night_medications' => $validated['shift_type'] === 'night' ? $validated['night_medications'] : null,
        'seizure_respiratory_events' => $validated['shift_type'] === 'night' ? $validated['seizure_respiratory_events'] : null,
        'morning_priorities' => $validated['shift_type'] === 'night' ? $validated['morning_priorities'] : null,
        'recorded_at' => now(),
    ]);

    $shiftLabel = $validated['shift_type'] === 'day' ? 'Day' : 'Night';
    AuditTrail::record(
        'created',
        "Recorded {$shiftLabel} handover for {$record->name}",
        'handover',
        (string) $handover->id,
        $record->name,
        ['shift_date' => $validated['shift_date'], 'shift_type' => $validated['shift_type']],
        ['patient_url_key' => $record->url_key],
        $request,
    );

    return redirect()
        ->route('patients.handovers', $record->url_key)
        ->with('success', ucfirst($validated['shift_type']).' handover saved.');
})->middleware(['auth', 'verified'])->name('patients.handovers.store');

Route::get('/patients/{patient}/wound-care', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    $assessments = PatientWoundAssessment::query()
        ->where('patient_id', $record->id)
        ->with(['recordedBy:id,name,first_name,surname'])
        ->orderByDesc('recorded_at')
        ->orderByDesc('id')
        ->limit(100)
        ->get()
        ->map(fn (PatientWoundAssessment $row) => map_patient_wound_assessment($row))
        ->values();

    $latest = $assessments->first();

    return Inertia::render('PatientWoundCare', [
        'patientSlug' => $patient,
        'patient' => [
            'name' => $record->name,
            'ragStatus' => $record->rag_status,
        ],
        'assessments' => $assessments,
        'latestAssessment' => $latest,
        'chartData' => build_patient_wound_measurement_chart_series($record),
        'pressureGrades' => collect(PatientWoundAssessment::PRESSURE_GRADES)
            ->map(fn (string $grade) => [
                'value' => $grade,
                'label' => Str::of($grade)->replace('_', ' ')->title()->toString(),
            ])
            ->values(),
        'documentChecklistUrl' => route('patients.documents.show', [$patient, 'tissue-viability-checklist']),
        'bodyMapRegions' => collect(PatientWoundAssessment::BODY_MAP_REGIONS)
            ->map(fn (string $region) => [
                'value' => $region,
                'label' => Str::of($region)->replace('_', ' ')->title()->toString(),
            ])
            ->values(),
    ]);
})->middleware(['auth', 'verified'])->name('patients.wound-care');

Route::post('/patients/{patient}/wound-care', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'wound_site' => ['required', 'string', 'max:255'],
        'wound_type' => ['nullable', 'string', 'max:255'],
        'pressure_ulcer_grade' => ['nullable', 'string', 'in:'.implode(',', PatientWoundAssessment::PRESSURE_GRADES)],
        'length_cm' => ['nullable', 'numeric', 'between:0,100'],
        'width_cm' => ['nullable', 'numeric', 'between:0,100'],
        'depth_cm' => ['nullable', 'numeric', 'between:0,100'],
        'exudate' => ['nullable', 'string', 'max:2000'],
        'periwound_condition' => ['nullable', 'string', 'max:2000'],
        'pain_score' => ['nullable', 'integer', 'between:0,10'],
        'dressing_type' => ['nullable', 'string', 'max:2000'],
        'pressure_regime' => ['nullable', 'string', 'max:2000'],
        'infection_signs' => ['nullable', 'string', 'max:2000'],
        'escalation_required' => ['nullable', 'boolean'],
        'body_map_notes' => ['nullable', 'string', 'max:5000'],
        'body_map_region' => ['nullable', 'string', 'in:'.implode(',', PatientWoundAssessment::BODY_MAP_REGIONS)],
        'review_due_at' => ['nullable', 'date'],
        'plan_actions' => ['nullable', 'string', 'max:5000'],
        'photo' => ['nullable', 'image', 'max:5120'],
        'photo_base64' => ['nullable', 'string', 'max:7000000'],
        'photo_filename' => ['nullable', 'string', 'max:255'],
    ]);

    $photoPath = null;
    if (request()->hasFile('photo')) {
        $photoPath = request()->file('photo')->store('wound-photos/'.$patientRecord->id, 'public');
    } elseif (!empty($payload['photo_base64'])) {
        $decoded = base64_decode((string) $payload['photo_base64'], true);
        if ($decoded !== false) {
            $ext = pathinfo((string) ($payload['photo_filename'] ?? 'wound.jpg'), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'wound-photos/'.$patientRecord->id.'/'.Str::uuid().'.'.Str::lower($ext);
            Storage::disk('public')->put($filename, $decoded);
            $photoPath = $filename;
        }
    }

    $reviewDue = isset($payload['review_due_at'])
        ? Carbon::parse((string) $payload['review_due_at'])->toDateString()
        : now()->addDays(7)->toDateString();

    $assessment = PatientWoundAssessment::query()->create([
        'patient_id' => $patientRecord->id,
        'recorded_by_user_id' => request()->user()?->id,
        'recorded_at' => now(),
        'wound_site' => trim($payload['wound_site']),
        'wound_type' => trim((string) ($payload['wound_type'] ?? '')) ?: null,
        'pressure_ulcer_grade' => $payload['pressure_ulcer_grade'] ?? null,
        'length_cm' => $payload['length_cm'] ?? null,
        'width_cm' => $payload['width_cm'] ?? null,
        'depth_cm' => $payload['depth_cm'] ?? null,
        'exudate' => trim((string) ($payload['exudate'] ?? '')) ?: null,
        'periwound_condition' => trim((string) ($payload['periwound_condition'] ?? '')) ?: null,
        'pain_score' => $payload['pain_score'] ?? null,
        'dressing_type' => trim((string) ($payload['dressing_type'] ?? '')) ?: null,
        'pressure_regime' => trim((string) ($payload['pressure_regime'] ?? '')) ?: null,
        'infection_signs' => trim((string) ($payload['infection_signs'] ?? '')) ?: null,
        'escalation_required' => (bool) ($payload['escalation_required'] ?? false),
        'body_map_notes' => trim((string) ($payload['body_map_notes'] ?? '')) ?: null,
        'body_map_region' => $payload['body_map_region'] ?? null,
        'photo_path' => $photoPath,
        'review_due_at' => $reviewDue,
        'plan_actions' => trim((string) ($payload['plan_actions'] ?? '')) ?: null,
    ]);

    $alerts = evaluate_wound_assessment_alerts($assessment);

    AuditTrail::record(
        'created',
        'Recorded wound assessment for '.$patientRecord->name,
        'wound_assessment',
        (string) $assessment->id,
        $patientRecord->name,
        [
            'wound_site' => $assessment->wound_site,
            'escalation_required' => $assessment->escalation_required,
            'alerts' => $alerts,
        ],
        ['patient_url_key' => $patient],
    );

    $flashMessage = 'Wound assessment recorded successfully.';
    if (!empty($alerts)) {
        $flashMessage .= ' Alert: '.implode(' ', array_slice($alerts, 0, 2));
    }

    return redirect()
        ->route('patients.wound-care', $patientRecord->url_key)
        ->with('success', $flashMessage);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.wound-care.store');

Route::get('/patients/{patient}/care-plans', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    return Inertia::render('PatientCarePlans', [
        'patientSlug' => $patient,
        'patient' => [
            'name' => $record->name,
            'reference' => $record->reference ?? 'Not assigned',
            'dob' => $record->dob ?? 'Not available',
            'allergies' => is_array($record->allergies) ? $record->allergies : [],
        ],
        'carePlans' => build_patient_care_plan_cards($patient),
    ]);
})->middleware(['auth', 'verified'])->name('patients.careplans');

Route::get('/patients/{patient}/care-plans/{plan}', function (string $patient, string $plan) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $savedCarePlan = PatientCarePlanForm::query()
        ->where('patient_slug', $patient)
        ->where('plan_slug', $plan)
        ->first();

    return Inertia::render('PatientCarePlanDetail', [
        'patientSlug' => $patient,
        'planSlug' => $plan,
        'patient' => [
            'name' => $record->name,
            'reference' => $record->reference ?? 'Not assigned',
            'dob' => $record->dob ?? 'Not available',
            'allergies' => is_array($record->allergies) ? $record->allergies : [],
        ],
        'initialSnapshot' => $savedCarePlan?->data ?? [],
    ]);
})->middleware(['auth', 'verified'])->name('patients.careplans.show');

Route::post('/patients/{patient}/care-plans/{plan}', function (string $patient, string $plan) {
    $user = request()->user();
    $canEditCarePlan = user_has_primary_role($user, ['super_admin', 'admin']);
    abort_unless($canEditCarePlan, 403, 'You do not have permission to update this care plan.');

    $payload = request()->validate([
        'data' => ['required', 'array'],
        'status' => ['nullable', 'string', 'in:draft,submitted,reviewed'],
    ]);

    $data = $payload['data'];
    validate_care_plan_payload_shape($plan, $data);

    $status = $payload['status'] ?? 'submitted';
    $submittedAt = now();
    $schemaVersion = care_plan_schema_version($plan);
    $summaryPayload = care_plan_summary_payload($plan, $data);

    DB::transaction(function () use (
        $patient,
        $plan,
        $data,
        $status,
        $submittedAt,
        $schemaVersion,
        $user,
        $summaryPayload
    ) {
        $carePlan = PatientCarePlanForm::query()->updateOrCreate(
            [
                'patient_slug' => $patient,
                'plan_slug' => $plan,
            ],
            [
                'data' => $data,
                'schema_version' => $schemaVersion,
                'status' => $status,
                'submitted_at' => $submittedAt,
                'submitted_by_user_id' => $user?->id,
                'updated_by_user_id' => $user?->id,
            ],
        );

        PatientCarePlanSummary::query()->updateOrCreate(
            [
                'patient_slug' => $patient,
                'plan_slug' => $plan,
            ],
            [
                'snapshot_id' => $carePlan->id,
                'schema_version' => $schemaVersion,
                'status' => $status,
                'submitted_at' => $submittedAt,
                'submitted_by_user_id' => $user?->id,
                'key_fields' => $summaryPayload['key_fields'],
                'data_excerpt' => $summaryPayload['data_excerpt'],
            ],
        );
    });

    $record = Patient::query()->where('url_key', $patient)->first();
    AuditTrail::record(
        'updated',
        'Saved care plan "'.$plan.'" for '.($record?->name ?? $patient),
        'care_plan',
        $patient.':'.$plan,
        $record?->name,
        ['status' => $status, 'plan_slug' => $plan],
        ['patient_url_key' => $patient],
    );

    return redirect()->back()->with('success', 'Care plan saved successfully.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager'])->name('patients.careplans.save');

Route::get('/patients/{patient}/risk-assessments', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    $saved = PatientRiskAssessment::query()
        ->where('patient_id', $record->id)
        ->get()
        ->keyBy('risk_slug');

    $updaterIds = $saved->pluck('updated_by_user_id')->filter()->unique()->values();
    $authorNames = User::query()
        ->whereIn('id', $updaterIds)
        ->get(['id', 'name', 'first_name', 'surname'])
        ->mapWithKeys(function (User $user) {
            $name = trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))));

            return [$user->id => $name !== '' ? $name : 'Unknown user'];
        });

    $assessments = collect(risk_assessment_templates())
        ->map(function (array $template) use ($saved, $authorNames) {
            $row = $saved->get($template['slug']);

            return map_patient_risk_assessment(
                $row,
                $template,
                $row ? ($authorNames[$row->updated_by_user_id] ?? null) : null,
            );
        })
        ->values();

    return Inertia::render('PatientRiskAssessments', [
        'patientSlug' => $patient,
        'patient' => ['name' => $record->name],
        'assessments' => $assessments,
    ]);
})->middleware(['auth', 'verified'])->name('patients.risks');

Route::get('/patients/{patient}/risk-assessments/{risk}', function (string $patient, string $risk) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $template = risk_assessment_template($risk);
    abort_unless($template, 404);

    $assessment = PatientRiskAssessment::query()
        ->where('patient_id', $record->id)
        ->where('risk_slug', $risk)
        ->first();

    $authorName = null;
    if ($assessment?->updated_by_user_id) {
        $updater = User::query()->find($assessment->updated_by_user_id, ['id', 'name', 'first_name', 'surname']);
        if ($updater) {
            $authorName = trim((string) ($updater->name ?: (($updater->first_name ?? '').' '.($updater->surname ?? ''))));
            if ($authorName === '') {
                $authorName = 'Unknown user';
            }
        }
    }

    $versions = $assessment ? list_risk_assessment_versions($assessment) : [];

    return Inertia::render('PatientRiskAssessmentDetail', [
        'patientSlug' => $patient,
        'patient' => ['name' => $record->name],
        'riskSlug' => $risk,
        'assessment' => map_patient_risk_assessment($assessment, $template, $authorName),
        'versions' => $versions,
        'canExportPdf' => $assessment !== null,
        'levelOptions' => collect(PatientRiskAssessment::LEVELS)
            ->map(fn (string $level) => ['value' => $level, 'label' => Str::of($level)->title()->toString()])
            ->values(),
        'statusOptions' => collect(PatientRiskAssessment::STATUSES)
            ->map(fn (string $status) => ['value' => $status, 'label' => Str::of($status)->replace('_', ' ')->title()->toString()])
            ->values(),
    ]);
})->middleware(['auth', 'verified'])->name('patients.risks.show');

Route::get('/patients/{patient}/risk-assessments/{risk}/pdf', function (string $patient, string $risk) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $template = risk_assessment_template($risk);
    abort_unless($template, 404);

    $assessment = PatientRiskAssessment::query()
        ->where('patient_id', $record->id)
        ->where('risk_slug', $risk)
        ->firstOrFail();

    $authorName = user_display_name(
        $assessment->updated_by_user_id
            ? User::query()->find($assessment->updated_by_user_id, ['id', 'name', 'first_name', 'surname'])
            : null
    );

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.risk-assessment-pdf', [
        'patient' => $record,
        'title' => $template['title'],
        'suggestedControls' => $template['suggestedControls'],
        'assessment' => build_risk_assessment_pdf_payload($record, $assessment, $template, $authorName),
        'generatedBy' => user_display_name(request()->user()) ?? 'System',
    ]);

    $filename = 'AlloCare-Risk-'.Str::slug($record->name).'-'.Str::slug($risk).'.pdf';

    return $pdf->download($filename);
})->middleware(['auth', 'verified'])->name('patients.risks.pdf');

Route::post('/patients/{patient}/risk-assessments/{risk}', function (string $patient, string $risk, Request $request) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $template = risk_assessment_template($risk);
    abort_unless($template, 404);

    $validated = $request->validate([
        'risk_level' => ['required', 'string', 'in:'.implode(',', PatientRiskAssessment::LEVELS)],
        'status' => ['required', 'string', 'in:'.implode(',', PatientRiskAssessment::STATUSES)],
        'triggers' => ['nullable', 'string', 'max:5000'],
        'current_controls' => ['nullable', 'string', 'max:5000'],
        'mitigation_plan' => ['nullable', 'string', 'max:5000'],
        'owner_name' => ['nullable', 'string', 'max:255'],
        'last_reviewed_at' => ['nullable', 'date'],
        'next_review_due_at' => ['nullable', 'date'],
        'review_cycle_months' => ['nullable', 'integer', 'min:1', 'max:24'],
    ]);

    $lastReviewed = isset($validated['last_reviewed_at'])
        ? Carbon::parse((string) $validated['last_reviewed_at'])->toDateString()
        : now()->toDateString();
    $cycleMonths = $validated['review_cycle_months'] ?? 3;
    $nextDue = isset($validated['next_review_due_at'])
        ? Carbon::parse((string) $validated['next_review_due_at'])->toDateString()
        : Carbon::parse($lastReviewed)->addMonths($cycleMonths)->toDateString();

    $assessment = PatientRiskAssessment::query()->updateOrCreate(
        ['patient_id' => $record->id, 'risk_slug' => $risk],
        [
            'risk_level' => $validated['risk_level'],
            'status' => $validated['status'],
            'triggers' => trim((string) ($validated['triggers'] ?? '')) ?: null,
            'current_controls' => trim((string) ($validated['current_controls'] ?? '')) ?: null,
            'mitigation_plan' => trim((string) ($validated['mitigation_plan'] ?? '')) ?: null,
            'owner_name' => trim((string) ($validated['owner_name'] ?? '')) ?: null,
            'last_reviewed_at' => $lastReviewed,
            'next_review_due_at' => $nextDue,
            'review_cycle_months' => $cycleMonths,
            'reviewed_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ],
    );

    record_risk_assessment_version_if_changed($assessment, $request->user());

    AuditTrail::record(
        'updated',
        'Saved '.$template['title'].' assessment for '.$record->name,
        'risk_assessment',
        (string) $assessment->id,
        $record->name,
        ['risk_slug' => $risk, 'risk_level' => $validated['risk_level'], 'status' => $validated['status']],
        ['patient_url_key' => $record->url_key],
        $request,
    );

    return redirect()
        ->route('patients.risks.show', ['patient' => $patient, 'risk' => $risk])
        ->with('success', 'Risk assessment saved.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager'])->name('patients.risks.save');

Route::post('/patients/{patient}/risk-assessments/{risk}/restore-version', function (string $patient, string $risk, Request $request) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $template = risk_assessment_template($risk);
    abort_unless($template, 404);

    $validated = $request->validate([
        'version_id' => ['required', 'integer'],
    ]);

    $assessment = PatientRiskAssessment::query()
        ->where('patient_id', $record->id)
        ->where('risk_slug', $risk)
        ->firstOrFail();

    abort_unless(Schema::hasTable('patient_risk_assessment_versions'), 404);

    $version = PatientRiskAssessmentVersion::query()
        ->where('id', $validated['version_id'])
        ->where('patient_risk_assessment_id', $assessment->id)
        ->firstOrFail();

    $snapshot = $version->snapshot;
    abort_unless(is_array($snapshot) && $snapshot !== [], 422, 'Version snapshot is invalid.');

    $restoredFrom = $version->recorded_at?->format('d M Y H:i') ?? 'selected version';
    apply_risk_assessment_snapshot($assessment, $snapshot, $request->user());
    $assessment->refresh();

    record_risk_assessment_version_forced(
        $assessment,
        $request->user(),
        'Restored from version recorded '.$restoredFrom,
        true,
    );

    AuditTrail::record(
        'updated',
        'Restored '.$template['title'].' assessment for '.$record->name.' from version history',
        'risk_assessment',
        (string) $assessment->id,
        $record->name,
        ['version_id' => $version->id, 'restored_from' => $restoredFrom],
        ['patient_url_key' => $record->url_key],
        $request,
    );

    return redirect()
        ->route('patients.risks.show', ['patient' => $patient, 'risk' => $risk])
        ->with('success', 'Assessment restored from '.$restoredFrom.'.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager'])->name('patients.risks.restore-version');

Route::get('/patients/{patient}/mar', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();

    $activeMeds = PatientMedication::query()->where('patient_id', $patientRecord->id)->where('active', true)->count();
    $prnCount = PatientMedication::query()->where('patient_id', $patientRecord->id)->where('active', true)->where('is_prn', true)->count();
    $controlledCount = PatientMedication::query()->where('patient_id', $patientRecord->id)->where('active', true)->where('is_controlled', true)->count();

    $todayAdmins = MedicationAdministration::query()
        ->where('patient_id', $patientRecord->id)
        ->whereDate('created_at', now()->toDateString())
        ->get();

    $givenToday = $todayAdmins->where('status', 'given')->count();
    $refusedToday = $todayAdmins->where('status', 'refused')->count();
    $omittedToday = $todayAdmins->where('status', 'omitted')->count();
    $delayedToday = $todayAdmins->where('status', 'delayed')->count();
    $dueToday = $activeMeds - $givenToday;

    $overdueReminders = MedicationReminder::query()
        ->where('patient_id', $patientRecord->id)
        ->where('dismissed', false)
        ->where('due_at', '<', now())
        ->whereDate('due_at', now()->toDateString())
        ->count();

    return Inertia::render('PatientMAR', [
        'patientSlug' => $patient,
        'stats' => [
            'activeMeds' => $activeMeds,
            'prnCount' => $prnCount,
            'controlledCount' => $controlledCount,
            'givenToday' => $givenToday,
            'refusedToday' => $refusedToday,
            'omittedToday' => $omittedToday,
            'delayedToday' => $delayedToday,
            'dueToday' => max(0, $dueToday),
            'overdueReminders' => $overdueReminders,
        ],
    ]);
})->middleware(['auth', 'verified'])->name('patients.mar');

Route::get('/patients/{patient}/mar/monthly-chart/pdf', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $monthParam = request('month');
    $month = $monthParam
        ? Carbon::parse($monthParam.'-01')->startOfMonth()
        : now()->startOfMonth();

    $rows = build_patient_mar_chart_rows($patientRecord, $month);
    $days = range(1, $month->daysInMonth);

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.mar-chart-pdf', [
        'patient' => $patientRecord,
        'month' => $month,
        'days' => $days,
        'rows' => $rows,
    ])->setPaper('a4', 'landscape');

    $filename = 'AlloCare-MAR-'.Str::slug($patientRecord->name).'-'.$month->format('Y-m').'.pdf';

    return $pdf->download($filename);
})->middleware(['auth', 'verified'])->name('patients.mar.monthly-chart.pdf');

Route::get('/patients/{patient}/mar/{mar}', function (string $patient, string $mar) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $medications = PatientMedication::query()
        ->where('patient_id', $patientRecord->id)
        ->where('active', true)
        ->where('is_prn', false)
        ->orderBy('scheduled_time')
        ->get()
        ->map(function ($medication) use ($mar) {
            $latestAdministration = MedicationAdministration::query()
                ->with('administeredBy:id,name,first_name,surname')
                ->where('patient_medication_id', $medication->id)
                ->where('source_mar_slug', $mar)
                ->latest('id')
                ->first();

            $administeredByName = '-';
            if ($latestAdministration) {
                $administeredByName = trim((string) ($latestAdministration->administeredBy?->name ?? ''));
                if ($administeredByName === '') {
                    $administeredByName = trim((string) (($latestAdministration->administeredBy?->first_name ?? '').' '.($latestAdministration->administeredBy?->surname ?? '')));
                }
                if ($administeredByName === '') {
                    $administeredByName = '-';
                }
            }

            $witnessName = $latestAdministration?->witness_name;
            if (!$witnessName && $latestAdministration?->witness_user_id) {
                $latestAdministration->loadMissing('witness:id,name,first_name,surname');
                $witnessName = trim((string) ($latestAdministration->witness?->name ?? ''));
            }

            return [
                'id' => $medication->id,
                'medicine' => $medication->name,
                'time' => $medication->scheduled_time ? Carbon::createFromFormat('H:i:s', (string) $medication->scheduled_time)->format('H:i') : '',
                'route' => $medication->route ?? '',
                'dose' => $medication->dose ?? '',
                'status' => Str::title((string) ($latestAdministration?->status ?? 'Due')),
                'by' => $administeredByName,
                'reason' => $latestAdministration?->reason,
                'witness_user_id' => $latestAdministration?->witness_user_id,
                'witness_name' => $witnessName,
                'is_controlled' => $medication->is_controlled,
                'is_prn' => $medication->is_prn,
            ];
        })
        ->values();

    $prnMedications = PatientMedication::query()
        ->where('patient_id', $patientRecord->id)
        ->where('active', true)
        ->where('is_prn', true)
        ->get()
        ->map(function ($med) {
            $todayCount = MedicationAdministration::query()
                ->where('patient_medication_id', $med->id)
                ->where('status', 'given')
                ->whereDate('administered_at', now()->toDateString())
                ->count();
            return [
                'id' => $med->id,
                'name' => $med->name,
                'dose' => $med->dose ?? '-',
                'route' => $med->route ?? '-',
                'prn_indication' => $med->prn_indication,
                'prn_max_daily_doses' => $med->prn_max_daily_doses,
                'today_count' => $todayCount,
            ];
        })
        ->values();

    $reminders = MedicationReminder::query()
        ->where('patient_id', $patientRecord->id)
        ->where('dismissed', false)
        ->whereDate('due_at', now()->toDateString())
        ->with('medication:id,name,dose,route')
        ->orderBy('due_at')
        ->get()
        ->map(fn ($r) => [
            'id' => $r->id,
            'medication_name' => $r->medication?->name ?? '-',
            'dose' => $r->medication?->dose ?? '-',
            'due_at' => $r->due_at->format('H:i'),
            'is_overdue' => $r->due_at->isPast(),
        ])
        ->values();

    $controlledStock = PatientMedication::query()
        ->where('patient_id', $patientRecord->id)
        ->where('active', true)
        ->where('is_controlled', true)
        ->with('stock')
        ->orderBy('name')
        ->get()
        ->map(fn (PatientMedication $med) => map_medication_stock($med->stock, $med))
        ->values();

    $inactiveMedications = PatientMedication::query()
        ->where('patient_id', $patientRecord->id)
        ->where('active', false)
        ->orderBy('name')
        ->get()
        ->map(fn (PatientMedication $med) => [
            'id' => $med->id,
            'name' => $med->name,
            'dose' => $med->dose,
            'route' => $med->route,
            'frequency' => $med->frequency,
            'is_prn' => $med->is_prn,
            'is_controlled' => $med->is_controlled,
            'deactivatedAtLabel' => $med->updated_at?->format('d M Y, H:i'),
        ])
        ->values();

    return Inertia::render('PatientMARDetail', [
        'patientSlug' => $patient,
        'marSlug' => $mar,
        'initialRows' => $medications,
        'prnMedications' => $prnMedications,
        'reminders' => $reminders,
        'witnessStaff' => list_mar_witness_staff(),
        'controlledStock' => $controlledStock,
        'inactiveMedications' => $inactiveMedications,
        'canManageMedications' => user_has_primary_role(request()->user(), ['super_admin', 'admin', 'care_manager', 'supervisor']),
    ]);
})->middleware(['auth', 'verified'])->name('patients.mar.show');

Route::post('/patients/{patient}/mar/{mar}', function (string $patient, string $mar) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'rows' => ['required', 'array'],
        'rows.*.id' => ['nullable', 'integer'],
        'rows.*.medicine' => ['required', 'string', 'max:255'],
        'rows.*.time' => ['nullable', 'date_format:H:i'],
        'rows.*.route' => ['nullable', 'string', 'max:100'],
        'rows.*.dose' => ['nullable', 'string', 'max:100'],
        'rows.*.status' => ['required', 'string', 'in:Given,Due,Refused,Omitted,Delayed,Self-Administered'],
        'rows.*.reason' => ['nullable', 'string', 'max:500'],
        'rows.*.witness_name' => ['nullable', 'string', 'max:255'],
        'rows.*.witness_user_id' => ['nullable', 'integer', 'exists:users,id'],
        'rows.*.is_prn_dose' => ['nullable', 'boolean'],
    ]);

    $errors = [];
    $currentUserId = request()->user()?->id;

    foreach ($payload['rows'] as $idx => $row) {
        $status = strtolower(str_replace('-', '_', (string) $row['status']));

        $medication = null;
        if (!empty($row['id'])) {
            $medication = PatientMedication::query()
                ->where('patient_id', $patientRecord->id)
                ->where('id', (int) $row['id'])
                ->first();
        }
        if (!$medication) {
            $medication = PatientMedication::query()->create([
                'patient_id' => $patientRecord->id,
                'name' => $row['medicine'],
                'route' => $row['route'] ?? null,
                'dose' => $row['dose'] ?? null,
                'scheduled_time' => !empty($row['time']) ? $row['time'].':00' : null,
                'created_by_user_id' => request()->user()?->id,
            ]);

            if (!empty($row['time'])) {
                $dueAt = Carbon::parse(now()->toDateString().' '.$row['time']);
                MedicationReminder::query()->create([
                    'patient_id' => $patientRecord->id,
                    'patient_medication_id' => $medication->id,
                    'due_at' => $dueAt,
                ]);
            }
        } else {
            $medication->update([
                'name' => $row['medicine'],
                'route' => $row['route'] ?? null,
                'dose' => $row['dose'] ?? null,
                'scheduled_time' => !empty($row['time']) ? $row['time'].':00' : null,
            ]);
        }

        if (in_array($status, ['refused', 'omitted', 'delayed']) && empty($row['reason'])) {
            $errors[] = "Row {$idx}: A reason is required when status is refused, omitted, or delayed.";
            continue;
        }

        $witnessUser = null;
        if ($medication->is_controlled && $status === 'given') {
            $witnessUserId = !empty($row['witness_user_id']) ? (int) $row['witness_user_id'] : null;
            if (!$witnessUserId) {
                $errors[] = "Row {$idx}: '{$medication->name}' requires a registered witness (different staff member).";
                continue;
            }
            if ($witnessUserId === $currentUserId) {
                $errors[] = "Row {$idx}: Witness must be a different staff member than the administrator.";
                continue;
            }
            $witnessUser = User::query()->find($witnessUserId);
            if (!$witnessUser) {
                $errors[] = "Row {$idx}: Selected witness not found.";
                continue;
            }
            $stock = MedicationStock::query()->where('patient_medication_id', $medication->id)->first();
            if ($stock && (float) $stock->balance <= 0) {
                $errors[] = "Row {$idx}: No controlled drug stock balance for '{$medication->name}'. Receive stock before administering.";
                continue;
            }
        }

        if ($medication->is_prn && $status === 'given') {
            $maxDaily = $medication->prn_max_daily_doses;
            if ($maxDaily) {
                $todayCount = MedicationAdministration::query()
                    ->where('patient_medication_id', $medication->id)
                    ->where('status', 'given')
                    ->whereDate('administered_at', now()->toDateString())
                    ->count();
                if ($todayCount >= $maxDaily) {
                    $errors[] = "Row {$idx}: '{$medication->name}' has reached its maximum daily PRN dose limit ({$maxDaily}).";
                    continue;
                }
            }
        }

        $scheduledFor = null;
        if (!empty($row['time'])) {
            $scheduledFor = Carbon::parse(now()->toDateString().' '.$row['time']);
        }

        $witnessName = $witnessUser
            ? trim((string) ($witnessUser->name ?: (($witnessUser->first_name ?? '').' '.($witnessUser->surname ?? ''))))
            : ($row['witness_name'] ?? null);

        $administration = MedicationAdministration::query()->create([
            'patient_id' => $patientRecord->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => request()->user()?->id,
            'status' => $status,
            'administered_at' => in_array($status, ['given', 'self_administered', 'delayed'], true) ? now() : null,
            'scheduled_for' => $scheduledFor,
            'source_mar_slug' => $mar,
            'reason' => $row['reason'] ?? null,
            'witness_user_id' => $witnessUser?->id,
            'witness_name' => $witnessName ?: null,
        ]);

        if ($medication->is_controlled && $status === 'given') {
            record_medication_stock_movement(
                $medication,
                MedicationStockMovement::TYPE_ADMINISTRATION,
                -1,
                request()->user(),
                'eMAR administration #'.$administration->id,
                $administration->id,
            );
        }

        if ($status !== 'due') {
            MedicationReminder::query()
                ->where('patient_medication_id', $medication->id)
                ->where('dismissed', false)
                ->whereDate('due_at', now()->toDateString())
                ->update([
                    'dismissed' => true,
                    'dismissed_by_user_id' => request()->user()?->id,
                ]);
        }
    }

    if (!empty($errors)) {
        return redirect()->back()->withErrors(['mar' => implode(' ', $errors)]);
    }

    AuditTrail::record(
        'updated',
        'Recorded eMAR administrations for '.$patientRecord->name,
        'medication',
        $patient.':'.$mar,
        $patientRecord->name,
        ['row_count' => count($payload['rows'])],
        ['patient_url_key' => $patient],
    );

    return redirect()->back()->with('success', 'eMAR saved successfully.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.mar.save');

Route::post('/patients/{patient}/mar/{mar}/clear-today', function (string $patient, string $mar) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $today = now()->toDateString();

    $administrations = MedicationAdministration::query()
        ->where('patient_id', $patientRecord->id)
        ->where(function ($query) use ($today) {
            $query->whereDate('administered_at', $today)
                ->orWhere(function ($inner) use ($today) {
                    $inner->whereNull('administered_at')
                        ->whereDate('created_at', $today);
                });
        })
        ->with('medication')
        ->get();

    $cleared = 0;
    foreach ($administrations as $administration) {
        $medication = $administration->medication;
        if ($medication?->is_controlled && $administration->status === 'given') {
            record_medication_stock_movement(
                $medication,
                MedicationStockMovement::TYPE_ADJUSTMENT,
                1,
                request()->user(),
                'Reversed controlled dose — cleared today\'s MAR #'.$administration->id,
            );
        }
        $administration->delete();
        $cleared++;
    }

    MedicationReminder::query()
        ->where('patient_id', $patientRecord->id)
        ->where('dismissed', true)
        ->whereDate('due_at', $today)
        ->update([
            'dismissed' => false,
            'dismissed_by_user_id' => null,
        ]);

    AuditTrail::record(
        'deleted',
        'Cleared today\'s eMAR entries for '.$patientRecord->name.' ('.$mar.')',
        'medication',
        $patient.':'.$mar,
        $patientRecord->name,
        ['cleared_count' => $cleared, 'date' => $today],
        ['patient_url_key' => $patient],
        request(),
    );

    return redirect()
        ->route('patients.mar.show', ['patient' => $patient, 'mar' => $mar])
        ->with('success', $cleared > 0
            ? "Cleared {$cleared} administration record(s) for today. Rows will show as Due."
            : 'No administration records found for today.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('patients.mar.clear-today');

Route::post('/patients/{patient}/medications/{medication}/deactivate', function (string $patient, PatientMedication $medication) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    abort_unless($medication->patient_id === $patientRecord->id, 404);

    $mar = request('mar');
    $redirect = (is_string($mar) && $mar !== '')
        ? redirect()->route('patients.mar.show', ['patient' => $patient, 'mar' => $mar])
        : redirect()->route('patients.mar', $patient);

    if (!$medication->active) {
        return $redirect->with('success', "'{$medication->name}' is already inactive.");
    }

    $medication->update(['active' => false]);

    AuditTrail::record(
        'updated',
        "Deactivated medication '{$medication->name}' for {$patientRecord->name}",
        'medication',
        (string) $medication->id,
        $medication->name,
        ['active' => false],
        ['patient_url_key' => $patient],
        request(),
    );

    return $redirect->with('success', "'{$medication->name}' removed from active eMAR.");
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('patients.medications.deactivate');

Route::post('/patients/{patient}/medications/{medication}/reactivate', function (string $patient, PatientMedication $medication) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    abort_unless($medication->patient_id === $patientRecord->id, 404);

    $mar = request('mar');
    $redirect = (is_string($mar) && $mar !== '')
        ? redirect()->route('patients.mar.show', ['patient' => $patient, 'mar' => $mar])
        : redirect()->route('patients.mar', $patient);

    if ($medication->active) {
        return $redirect->with('success', "'{$medication->name}' is already active on the eMAR.");
    }

    $medication->update(['active' => true]);

    AuditTrail::record(
        'updated',
        "Reactivated medication '{$medication->name}' for {$patientRecord->name}",
        'medication',
        (string) $medication->id,
        $medication->name,
        ['active' => true],
        ['patient_url_key' => $patient],
        request(),
    );

    return $redirect->with('success', "'{$medication->name}' restored to the active eMAR.");
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('patients.medications.reactivate');

Route::post('/patients/{patient}/medications/{medication}/stock', function (string $patient, PatientMedication $medication) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    abort_unless($medication->patient_id === $patientRecord->id && $medication->is_controlled, 404);

    $validated = request()->validate([
        'movement_type' => ['required', 'string', 'in:'.implode(',', [
            MedicationStockMovement::TYPE_RECEIPT,
            MedicationStockMovement::TYPE_ADJUSTMENT,
            MedicationStockMovement::TYPE_DESTRUCTION,
            MedicationStockMovement::TYPE_RECONCILIATION,
        ])],
        'quantity' => ['required', 'numeric', 'not_in:0'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $quantity = (float) $validated['quantity'];
    $type = $validated['movement_type'];

    if (in_array($type, [MedicationStockMovement::TYPE_RECEIPT, MedicationStockMovement::TYPE_RECONCILIATION], true) && $quantity < 0) {
        throw ValidationException::withMessages(['quantity' => 'Quantity must be positive for receipts and reconciliation.']);
    }

    $delta = in_array($type, [MedicationStockMovement::TYPE_ADMINISTRATION, MedicationStockMovement::TYPE_DESTRUCTION], true)
        ? -abs($quantity)
        : $quantity;

    record_medication_stock_movement(
        $medication,
        $type,
        $delta,
        request()->user(),
        trim((string) ($validated['notes'] ?? '')) ?: null,
    );

    if ($type === MedicationStockMovement::TYPE_RECONCILIATION) {
        MedicationStock::query()
            ->where('patient_medication_id', $medication->id)
            ->update([
                'reconciled_at' => now(),
                'reconciled_by_user_id' => request()->user()?->id,
            ]);
    }

    AuditTrail::record(
        'updated',
        'Updated controlled drug stock for '.$medication->name,
        'medication_stock',
        (string) $medication->id,
        $patientRecord->name,
        ['movement_type' => $type, 'quantity' => $quantity],
        ['patient_url_key' => $patient],
    );

    return redirect()->back()->with('success', 'Stock updated.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('patients.medications.stock');

Route::post('/patients/{patient}/medications', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    $frequencyPresets = [
        'once_daily' => ['08:00'],
        'twice_daily' => ['08:00', '20:00'],
        'three_times_daily' => ['08:00', '14:00', '22:00'],
        'four_times_daily' => ['06:00', '12:00', '18:00', '22:00'],
        'every_8h' => ['06:00', '14:00', '22:00'],
        'every_12h' => ['08:00', '20:00'],
        'weekly' => ['08:00'],
        'custom' => null,
    ];

    $validated = request()->validate([
        'name' => ['required', 'string', 'max:255'],
        'route' => ['nullable', 'string', 'max:100'],
        'dose' => ['nullable', 'string', 'max:100'],
        'frequency' => ['required', 'string', 'in:'.implode(',', array_keys($frequencyPresets))],
        'scheduled_times' => ['nullable', 'array'],
        'scheduled_times.*' => ['string', 'date_format:H:i'],
        'start_date' => ['nullable', 'date'],
        'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        'is_prn' => ['boolean'],
        'is_controlled' => ['boolean'],
        'prn_indication' => ['nullable', 'string', 'max:255'],
        'prn_max_daily_doses' => ['nullable', 'integer', 'min:1'],
    ]);

    $frequency = $validated['frequency'];
    $isPrn = $validated['is_prn'] ?? false;

    if ($isPrn) {
        $scheduledTimes = null;
    } elseif ($frequency === 'custom') {
        $scheduledTimes = $validated['scheduled_times'] ?? [];
    } else {
        $scheduledTimes = $frequencyPresets[$frequency] ?? [];
    }

    $medication = PatientMedication::query()->create([
        'patient_id' => $record->id,
        'name' => $validated['name'],
        'route' => $validated['route'] ?? null,
        'dose' => $validated['dose'] ?? null,
        'frequency' => $frequency,
        'scheduled_times' => $scheduledTimes,
        'scheduled_time' => $scheduledTimes[0] ?? null,
        'start_date' => $validated['start_date'] ?? now()->toDateString(),
        'end_date' => $validated['end_date'] ?? null,
        'is_prn' => $isPrn,
        'is_controlled' => $validated['is_controlled'] ?? false,
        'prn_indication' => $validated['prn_indication'] ?? null,
        'prn_max_daily_doses' => $validated['prn_max_daily_doses'] ?? null,
        'active' => true,
        'created_by_user_id' => request()->user()?->id,
    ]);

    if (!$isPrn && is_array($scheduledTimes)) {
        $today = now()->toDateString();
        foreach ($scheduledTimes as $time) {
            $dueAt = Carbon::parse("{$today} {$time}");
            if ($dueAt->isFuture()) {
                MedicationReminder::query()->create([
                    'patient_id' => $record->id,
                    'patient_medication_id' => $medication->id,
                    'due_at' => $dueAt,
                ]);
            }
        }
    }

    if ($medication->is_controlled) {
        MedicationStock::query()->firstOrCreate(
            ['patient_medication_id' => $medication->id],
            ['balance' => 0, 'unit' => 'doses'],
        );
    }

    AuditTrail::record(
        'created',
        "Added medication '{$medication->name}' for patient",
        'medication',
        (string) $medication->id,
        $medication->name,
        null,
        ['patient_url_key' => $patient, 'frequency' => $frequency, 'is_prn' => $isPrn, 'is_controlled' => $validated['is_controlled'] ?? false],
    );

    return redirect()->back()->with('success', 'Medication added successfully.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.medications.store');

Route::patch('/patients/{patient}/medications/{medication}', function (string $patient, int $medication) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $med = PatientMedication::query()->where('patient_id', $record->id)->findOrFail($medication);

    $validated = request()->validate([
        'active' => ['sometimes', 'boolean'],
        'dose' => ['sometimes', 'nullable', 'string', 'max:100'],
        'route' => ['sometimes', 'nullable', 'string', 'max:100'],
        'end_date' => ['sometimes', 'nullable', 'date'],
    ]);

    $med->update($validated);

    AuditTrail::record(
        'updated',
        "Updated medication '{$med->name}'",
        'medication',
        (string) $med->id,
        $med->name,
        $validated,
        ['patient_url_key' => $patient],
    );

    return redirect()->back()->with('success', 'Medication updated.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.medications.update');

Route::get('/patients/{patient}/documents', function (string $patient) {
    return Inertia::render('PatientDocuments', [
        'patientSlug' => $patient,
    ]);
})->middleware(['auth', 'verified'])->name('patients.documents');

Route::get('/patients/{patient}/documents/{document}', function (string $patient, string $document) {
    $entry = PatientDocumentForm::query()
        ->where('patient_slug', $patient)
        ->where('document_slug', $document)
        ->first();

    $user = request()->user();
    $canEditDocumentForm = user_has_primary_role($user, ['admin', 'administrator', 'super_admin', 'staff', 'care_staff', 'support_staff']);

    return Inertia::render('PatientDocumentDetail', [
        'patientSlug' => $patient,
        'documentSlug' => $document,
        'initialFormData' => $entry?->data ?? [],
        'savedSubmittedAt' => $entry?->submitted_at?->toIso8601String(),
        'canEditDocumentForm' => $canEditDocumentForm,
    ]);
})->middleware(['auth', 'verified'])->name('patients.documents.show');

Route::post('/patients/{patient}/documents/{document}', function (string $patient, string $document) {
    $user = request()->user();
    $canEditDocumentForm = user_has_primary_role($user, ['admin', 'administrator', 'super_admin', 'staff', 'care_staff', 'support_staff']);

    abort_unless($canEditDocumentForm, 403, 'Only Admin and Staff can update this form.');

    $payload = request()->validate([
        'data' => ['required', 'array'],
    ]);

    PatientDocumentForm::query()->updateOrCreate(
        [
            'patient_slug' => $patient,
            'document_slug' => $document,
        ],
        [
            'data' => $payload['data'],
            'submitted_at' => now(),
            'updated_by_user_id' => $user?->id,
        ],
    );

    $record = Patient::query()->where('url_key', $patient)->first();
    AuditTrail::record(
        'updated',
        'Saved document "'.$document.'" for '.($record?->name ?? $patient),
        'document',
        $patient.':'.$document,
        $record?->name,
        null,
        ['patient_url_key' => $patient],
    );

    return redirect()->back()->with('success', 'Document saved successfully.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.documents.save');

Route::get('/patients/{patient}/incidents/create', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $snapshot = FormSnapshot::query()->where('form_key', "incident:{$patient}")->first();

    $snapshotData = $snapshot?->data ?? [];
    $alreadySubmitted = ($snapshotData['status'] ?? null) === 'Submitted';

    $user = request()->user();
    $reporterName = trim((string) (($user->first_name ?? '').' '.($user->surname ?? '')));
    if ($reporterName === '') {
        $reporterName = $user->name ?? '';
    }

    return Inertia::render('IncidentReport', [
        'patientSlug' => $patient,
        'incidentStatus' => $alreadySubmitted ? 'new' : ($snapshot ? 'draft' : 'new'),
        'initialSnapshot' => $alreadySubmitted ? [] : $snapshotData,
        'patientData' => [
            'name' => $record->name,
            'reference' => $record->reference ?? '#'.strtoupper($record->url_key),
            'dob' => $record->dob ?? 'Not available',
            'address' => $record->address ?? 'Not available',
            'allergies' => is_array($record->allergies) ? $record->allergies : [],
            'status' => $record->status,
        ],
        'reporterName' => $reporterName,
    ]);
})->middleware(['auth', 'verified'])->name('patients.incidents.create');

Route::get('/patients/{patient}/shift-check-in', function (string $patient) {
    $snapshot = FormSnapshot::query()->where('form_key', "shift-checkin:{$patient}")->first();
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $latestVitals = PatientVital::query()
        ->where('patient_id', $patientRecord->id)
        ->latest('recorded_at')
        ->latest('id')
        ->first();
    $nextVisit = PatientSchedule::query()
        ->where('patient_id', $patientRecord->id)
        ->where('end_at', '>=', now())
        ->orderBy('start_at')
        ->first();
    $activeVisit = resolve_patient_schedule_for_ecm($patientRecord, null);
    $medicationItems = PatientMedication::query()
        ->where('patient_id', $patientRecord->id)
        ->where('active', true)
        ->orderBy('scheduled_time')
        ->limit(4)
        ->get()
        ->map(function ($medication) {
            $scheduledTime = 'Due';
            if (!empty($medication->scheduled_time)) {
                $scheduledTime = Carbon::createFromFormat('H:i:s', (string) $medication->scheduled_time)->format('H:i');
            }

            return [
                'name' => $medication->name,
                'detail' => trim(((string) ($medication->dose ?? '')).' - '.((string) ($medication->route ?? 'Medication'))),
                'time' => $scheduledTime,
                'state' => 'Due',
            ];
        })
        ->values();
    $allergies = is_array($patientRecord->allergies) ? array_filter(array_map('trim', $patientRecord->allergies)) : [];
    $highRiskFlags = [];
    if (!empty($allergies)) {
        $highRiskFlags[] = 'Allergy Alert - '.implode(', ', array_slice($allergies, 0, 3));
    }
    if ($patientRecord->rag_status === 'red') {
        $highRiskFlags[] = 'RAG Red - High clinical risk.';
    } elseif ($patientRecord->rag_status === 'amber') {
        $highRiskFlags[] = 'RAG Amber - Monitor closely during visit.';
    }

    $visitTasks = $activeVisit ? load_schedule_visit_tasks($activeVisit) : [];

    return Inertia::render('ShiftCheckIn', [
        'patientSlug' => $patient,
        'initialSnapshot' => $snapshot?->data ?? [],
        'visitTasks' => $visitTasks,
        'patientContext' => [
            'name' => $patientRecord->name,
            'location' => $patientRecord->address ?: 'Location not provided',
            'latitude' => $patientRecord->latitude ? (float) $patientRecord->latitude : null,
            'longitude' => $patientRecord->longitude ? (float) $patientRecord->longitude : null,
            'scheduledStartAt' => $nextVisit?->start_at?->toIso8601String(),
            'scheduledEndAt' => $nextVisit?->end_at?->toIso8601String(),
            'scheduledWindow' => $nextVisit
                ? optional($nextVisit->start_at)->format('H:i').' - '.optional($nextVisit->end_at)->format('H:i')
                : 'Not scheduled',
            'activeScheduleId' => $activeVisit?->id,
            'highRiskFlags' => !empty($highRiskFlags) ? $highRiskFlags : ['No high-risk flags recorded.'],
        ],
        'medicationItems' => $medicationItems,
        'latestVitals' => $latestVitals ? [
            'heartRate' => $latestVitals->heart_rate,
            'bpSystolic' => $latestVitals->bp_systolic,
            'spo2' => $latestVitals->spo2,
        ] : null,
    ]);
})->middleware(['auth', 'verified'])->name('patients.shift-checkin');

Route::post('/patients/{patient}/shift-check-in/session/start', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'schedule_id' => ['nullable', 'integer'],
        'started_at' => ['nullable', 'date'],
        'gps_latitude' => ['nullable', 'numeric', 'between:-90,90'],
        'gps_longitude' => ['nullable', 'numeric', 'between:-180,180'],
    ]);

    $schedule = resolve_patient_schedule_for_ecm($patientRecord, isset($payload['schedule_id']) ? (int) $payload['schedule_id'] : null);

    if (!$schedule) {
        return response()->json(['ok' => false, 'message' => 'No schedule found for this patient.'], 422);
    }

    $startedAt = isset($payload['started_at']) ? Carbon::parse((string) $payload['started_at']) : now();
    $distance = ecm_distance_metres(
        $patientRecord->latitude ? (float) $patientRecord->latitude : null,
        $patientRecord->longitude ? (float) $patientRecord->longitude : null,
        isset($payload['gps_latitude']) ? (float) $payload['gps_latitude'] : null,
        isset($payload['gps_longitude']) ? (float) $payload['gps_longitude'] : null
    );
    $lateByMinutes = $startedAt->greaterThan($schedule->start_at) ? $schedule->start_at->diffInMinutes($startedAt) : 0;

    $schedule->update([
        'checked_in_at' => $startedAt,
        'check_in_latitude' => $payload['gps_latitude'] ?? null,
        'check_in_longitude' => $payload['gps_longitude'] ?? null,
        'check_in_distance_metres' => $distance,
        'late_by_minutes' => $lateByMinutes > 0 ? $lateByMinutes : null,
    ]);

    seed_schedule_visit_tasks($schedule);

    AuditTrail::record(
        'updated',
        'Recorded shift check-in for '.$patientRecord->name,
        'schedule',
        (string) $schedule->id,
        $patientRecord->name,
        [
            'checked_in_at' => $startedAt->toIso8601String(),
            'late_by_minutes' => $lateByMinutes,
            'check_in_distance_metres' => $distance,
        ],
        ['patient_url_key' => $patientRecord->url_key],
    );

    return response()->json([
        'ok' => true,
        'schedule_id' => $schedule->id,
        'late_by_minutes' => $lateByMinutes,
        'visit_tasks' => load_schedule_visit_tasks($schedule),
    ]);
})->middleware(['auth', 'verified'])->name('patients.shift-checkin.session.start');

Route::post('/schedules/{schedule}/visit-tasks', function (PatientSchedule $schedule) {
    $payload = request()->validate([
        'tasks' => ['required', 'array', 'min:1'],
        'tasks.*.id' => ['required', 'integer'],
        'tasks.*.outcome' => ['required', 'string', 'in:'.implode(',', ScheduleVisitTask::OUTCOMES)],
        'tasks.*.notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $user = request()->user();
    $taskIds = collect($payload['tasks'])->pluck('id')->map(fn ($id) => (int) $id)->all();
    $tasksById = ScheduleVisitTask::query()
        ->where('patient_schedule_id', $schedule->id)
        ->whereIn('id', $taskIds)
        ->get()
        ->keyBy('id');

    if ($tasksById->count() !== count($taskIds)) {
        return response()->json(['ok' => false, 'message' => 'One or more tasks are invalid for this visit.'], 422);
    }

    $recorded = [];
    foreach ($payload['tasks'] as $item) {
        $task = $tasksById->get((int) $item['id']);
        if (!$task) {
            continue;
        }

        $notes = trim((string) ($item['notes'] ?? ''));
        $task->update([
            'outcome' => $item['outcome'],
            'notes' => $notes !== '' ? $notes : null,
            'completed_at' => now(),
            'completed_by_user_id' => $user?->id,
        ]);

        $recorded[] = [
            'task_key' => $task->task_key,
            'outcome' => $task->outcome,
        ];
    }

    $patientName = $schedule->patient?->name ?? 'Unknown';
    AuditTrail::record(
        'updated',
        'Recorded visit task outcomes for '.$patientName,
        'schedule',
        (string) $schedule->id,
        $patientName,
        ['tasks' => $recorded],
        ['patient_url_key' => $schedule->patient?->url_key],
    );

    return response()->json([
        'ok' => true,
        'visit_tasks' => load_schedule_visit_tasks($schedule),
    ]);
})->middleware(['auth', 'verified'])->name('schedules.visit-tasks.store');

Route::post('/patients/{patient}/shift-check-in/session/end', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'schedule_id' => ['nullable', 'integer'],
        'ended_at' => ['nullable', 'date'],
        'gps_latitude' => ['nullable', 'numeric', 'between:-90,90'],
        'gps_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        'reason' => ['nullable', 'string', 'max:1000'],
    ]);

    $schedule = resolve_patient_schedule_for_ecm($patientRecord, isset($payload['schedule_id']) ? (int) $payload['schedule_id'] : null);

    if (!$schedule) {
        return response()->json(['ok' => false, 'message' => 'No schedule found for this patient.'], 422);
    }

    $endedAt = isset($payload['ended_at']) ? Carbon::parse((string) $payload['ended_at']) : now();
    $distance = ecm_distance_metres(
        $patientRecord->latitude ? (float) $patientRecord->latitude : null,
        $patientRecord->longitude ? (float) $patientRecord->longitude : null,
        isset($payload['gps_latitude']) ? (float) $payload['gps_latitude'] : null,
        isset($payload['gps_longitude']) ? (float) $payload['gps_longitude'] : null
    );
    $leftEarlyByMinutes = $endedAt->lessThan($schedule->end_at) ? $endedAt->diffInMinutes($schedule->end_at) : 0;

    $schedule->update([
        'checked_out_at' => $endedAt,
        'check_out_latitude' => $payload['gps_latitude'] ?? null,
        'check_out_longitude' => $payload['gps_longitude'] ?? null,
        'check_out_distance_metres' => $distance,
        'left_early_by_minutes' => $leftEarlyByMinutes > 0 ? $leftEarlyByMinutes : null,
        'notes' => trim((string) ($payload['reason'] ?? '')) !== '' ? trim((string) $payload['reason']) : $schedule->notes,
        'status' => $schedule->status ?: 'completed',
    ]);

    AuditTrail::record(
        'updated',
        'Recorded shift check-out for '.$patientRecord->name,
        'schedule',
        (string) $schedule->id,
        $patientRecord->name,
        [
            'checked_out_at' => $endedAt->toIso8601String(),
            'left_early_by_minutes' => $leftEarlyByMinutes,
            'check_out_distance_metres' => $distance,
        ],
        ['patient_url_key' => $patientRecord->url_key],
    );

    return response()->json(['ok' => true, 'schedule_id' => $schedule->id, 'left_early_by_minutes' => $leftEarlyByMinutes]);
})->middleware(['auth', 'verified'])->name('patients.shift-checkin.session.end');

Route::post('/patients/{patient}/vitals', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'heart_rate' => ['required', 'integer', 'between:20,260'],
        'bp_systolic' => ['required', 'integer', 'between:40,300'],
        'bp_diastolic' => ['nullable', 'integer', 'between:40,200'],
        'spo2' => ['required', 'integer', 'between:50,100'],
        'temperature_celsius' => ['nullable', 'numeric', 'between:30,45'],
        'blood_glucose_mmol' => ['nullable', 'numeric', 'between:1,35'],
        'weight_kg' => ['nullable', 'numeric', 'between:1,500'],
        'pain_score' => ['nullable', 'integer', 'between:0,10'],
        'other_observation' => ['nullable', 'string', 'max:5000'],
    ]);

    $otherObservation = trim((string) ($payload['other_observation'] ?? ''));

    $vital = PatientVital::query()->create([
        'patient_id' => $patientRecord->id,
        'heart_rate' => (int) $payload['heart_rate'],
        'bp_systolic' => (int) $payload['bp_systolic'],
        'bp_diastolic' => isset($payload['bp_diastolic']) ? (int) $payload['bp_diastolic'] : null,
        'spo2' => (int) $payload['spo2'],
        'temperature_celsius' => $payload['temperature_celsius'] ?? null,
        'blood_glucose_mmol' => $payload['blood_glucose_mmol'] ?? null,
        'weight_kg' => $payload['weight_kg'] ?? null,
        'pain_score' => $payload['pain_score'] ?? null,
        'other_observation' => $otherObservation !== '' ? $otherObservation : null,
        'recorded_at' => now(),
        'recorded_by_user_id' => request()->user()?->id,
    ]);

    $thresholdAlerts = evaluate_vital_threshold_alerts($vital);

    AuditTrail::record(
        'created',
        'Recorded clinical observation for '.$patientRecord->name,
        'vital',
        (string) $vital->id,
        $patientRecord->name,
        [
            'heart_rate' => $vital->heart_rate,
            'bp_systolic' => $vital->bp_systolic,
            'bp_diastolic' => $vital->bp_diastolic,
            'spo2' => $vital->spo2,
            'temperature_celsius' => $vital->temperature_celsius,
            'blood_glucose_mmol' => $vital->blood_glucose_mmol,
            'weight_kg' => $vital->weight_kg,
            'pain_score' => $vital->pain_score,
            'threshold_alerts' => $thresholdAlerts,
        ],
        ['patient_url_key' => $patient],
    );

    $flashMessage = 'Clinical observation recorded successfully.';
    if (!empty($thresholdAlerts)) {
        $flashMessage .= ' Alert: '.implode(' ', array_slice($thresholdAlerts, 0, 2));
    }

    return redirect()->back()->with('success', $flashMessage);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.vitals.store');

Route::post('/patients/{patient}/fluid-records', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'fluid_intake_ml' => ['nullable', 'integer', 'between:0,5000'],
        'fluid_output_ml' => ['nullable', 'integer', 'between:0,5000'],
        'fluid_type' => ['nullable', 'string', 'max:64'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $intake = $payload['fluid_intake_ml'] ?? null;
    $output = $payload['fluid_output_ml'] ?? null;
    if ($intake === null && $output === null) {
        throw ValidationException::withMessages([
            'fluid_intake_ml' => 'Enter fluid intake and/or output in ml.',
        ]);
    }

    $record = PatientFluidRecord::query()->create([
        'patient_id' => $patientRecord->id,
        'recorded_by_user_id' => request()->user()?->id,
        'recorded_at' => now(),
        'fluid_intake_ml' => $intake,
        'fluid_output_ml' => $output,
        'fluid_type' => trim((string) ($payload['fluid_type'] ?? '')) ?: null,
        'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
    ]);

    AuditTrail::record(
        'created',
        'Recorded fluid balance for '.$patientRecord->name,
        'fluid_record',
        (string) $record->id,
        $patientRecord->name,
        ['fluid_intake_ml' => $intake, 'fluid_output_ml' => $output],
        ['patient_url_key' => $patient],
    );

    $flashMessage = 'Fluid record saved.';
    foreach (evaluate_fluid_balance_alerts($patientRecord) as $alert) {
        $flashMessage .= ' '.$alert;
    }

    return redirect()
        ->route('patients.observations', $patientRecord->url_key)
        ->with('success', $flashMessage);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.fluid.store');

Route::post('/patients/{patient}/bowel-records', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'bowel_opened' => ['nullable', 'boolean'],
        'bristol_type' => ['nullable', 'integer', 'between:1,7'],
        'continence_status' => ['nullable', 'string', 'max:64'],
        'notes' => ['nullable', 'string', 'max:2000'],
    ]);

    $bristol = $payload['bristol_type'] ?? null;
    $notes = trim((string) ($payload['notes'] ?? ''));
    if ($bristol === null && $notes === '') {
        throw ValidationException::withMessages([
            'bristol_type' => 'Select a Bristol stool type or enter notes.',
        ]);
    }

    PatientBowelRecord::query()->create([
        'patient_id' => $patientRecord->id,
        'recorded_by_user_id' => request()->user()?->id,
        'recorded_at' => now(),
        'bowel_opened' => (bool) ($payload['bowel_opened'] ?? true),
        'bristol_type' => $bristol,
        'continence_status' => trim((string) ($payload['continence_status'] ?? '')) ?: null,
        'notes' => $notes !== '' ? $notes : null,
    ]);

    AuditTrail::record(
        'created',
        'Recorded bowel chart entry for '.$patientRecord->name,
        'bowel_record',
        (string) $patientRecord->id,
        $patientRecord->name,
        ['bristol_type' => $bristol],
        ['patient_url_key' => $patient],
    );

    return redirect()
        ->route('patients.observations', $patientRecord->url_key)
        ->with('success', 'Bowel chart entry saved.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor,care_worker'])->name('patients.bowel.store');

Route::get('/patients/{patient}/logs', function (string $patient) {
    abort_unless(AuditTrail::canViewReports(request()->user()), 403, 'You do not have permission to view audit history.');

    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    $events = [];
    if (Schema::hasTable('audit_events')) {
        $events = AuditEvent::query()
            ->where(function ($query) use ($patient) {
                $query
                    ->where(function ($scoped) use ($patient) {
                        $scoped->where('subject_type', 'patient')->where('subject_key', $patient);
                    })
                    ->orWhere('metadata->patient_url_key', $patient);
            })
            ->orderByDesc('id')
            ->limit(250)
            ->get()
            ->map(fn (AuditEvent $event) => AuditTrail::mapForUi($event))
            ->values()
            ->all();
    }

    return Inertia::render('PatientLogs', [
        'patientSlug' => $patient,
        'patientName' => $record->name,
        'events' => $events,
    ]);
})->middleware(['auth', 'verified'])->name('patients.logs');

Route::get('/patients/{patient}/contacts', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();

    return Inertia::render('PatientContacts', [
        'patientSlug' => $patient,
        'patientContactData' => [
            'profile' => [
                'name' => $record->name,
                'dob' => $record->dob ?? 'Not provided',
                'nhs' => $record->nhs_number ?? 'Not provided',
                'urgentTag' => strtoupper((string) ($record->rag_status ?? 'N/A')),
            ],
            'personal' => [
                [
                    'name' => $record->next_of_kin ?: 'Not provided',
                    'role' => 'Next of Kin',
                    'phone' => $record->next_of_kin_tel ?: 'Not provided',
                    'email' => $record->next_of_kin_email ?: 'Not provided',
                    'badge' => 'Primary',
                ],
                [
                    'name' => $record->other_relevant_people ?: 'Not provided',
                    'role' => 'Other Relevant People',
                    'phone' => 'Not provided',
                    'email' => 'Not provided',
                    'badge' => null,
                ],
            ],
            'professional' => [
                [
                    'name' => 'Social Services',
                    'role' => 'Local Authority Contact',
                    'phone' => $record->social_services_number ?: 'Not provided',
                    'email' => 'Not provided',
                    'badge' => 'Service',
                ],
            ],
        ],
    ]);
})->middleware(['auth', 'verified'])->name('patients.contacts');

Route::get('/employees', function () {
    $employees = User::query()
        ->latest()
        ->get()
        ->map(function ($user) {
            $rawStatus = strtolower(trim((string) ($user->account_status ?? 'active')));
            $normalizedStatus = $rawStatus === 'active' ? 'active' : 'inactive';

            return [
                'id' => $user->id,
                'name' => trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? '')))) ?: 'Unnamed staff',
                'role' => $user->primary_role ? Str::of($user->primary_role)->replace('_', ' ')->title()->toString() : 'Staff',
                'phone' => $user->phone ?? 'Not provided',
                'email' => $user->email ?? 'Not provided',
                'photoUrl' => $user->photo_path ? route('employees.photo', $user) : null,
                'status' => $normalizedStatus === 'active' ? 'Active' : 'Inactive',
                'statusValue' => $normalizedStatus,
                'lastLoginAt' => optional($user->last_login_at)->toIso8601String(),
                'lastLoginOs' => $user->last_login_os,
                'lastLoginAppVersion' => $user->last_login_app_version,
                'avatar' => 'bg-sky-300',
                'statusClass' => $normalizedStatus === 'active'
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-slate-200 text-slate-700',
            ];
        });

    return Inertia::render('Employees', [
        'employees' => $employees,
    ]);
})->middleware(['auth', 'verified'])->name('employees');

Route::patch('/employees/{user}/account-status', function (User $user) {
    $requestUser = request()->user();
    $isAdmin = user_has_primary_role($requestUser, ['admin', 'super_admin']);
    abort_unless($isAdmin, 403, 'Only admin users can update employee account status.');

    $payload = request()->validate([
        'account_status' => ['required', 'string', 'in:active,inactive'],
    ]);

    $previousStatus = $user->account_status;
    $user->update([
        'account_status' => $payload['account_status'],
    ]);

    $employeeName = AuditTrail::actorName($user) ?? 'Employee #'.$user->id;
    AuditTrail::record(
        'updated',
        'Changed account status for '.$employeeName,
        'employee',
        (string) $user->id,
        $employeeName,
        [
            'before' => ['account_status' => $previousStatus],
            'after' => ['account_status' => $payload['account_status']],
        ],
    );

    return redirect()->route('employees')->with('success', 'Employee account status updated.');
})->middleware(['auth', 'verified', 'role:super_admin,admin'])->name('employees.account-status');

Route::get('/employees/{user}/photo', function (User $user) {
    abort_unless($user->photo_path && Storage::disk('public')->exists($user->photo_path), 404);

    return Storage::disk('public')->response($user->photo_path);
})->middleware(['auth', 'verified'])->name('employees.photo');

Route::get('/employees/create', function () {
    $snapshot = FormSnapshot::query()->where('form_key', 'employee-create')->first();
    return Inertia::render('EmployeesCreate', [
        'initialSnapshot' => $snapshot?->data ?? [],
    ]);
})->middleware(['auth', 'verified', 'role:super_admin,admin'])->name('employees.create');

Route::post('/employees', function () {
    $payload = request()->validate([
        'title' => ['nullable', 'string', 'max:50'],
        'first_name' => ['required', 'string', 'max:255'],
        'surname' => ['required', 'string', 'max:255'],
        'date_of_birth' => ['nullable', 'string', 'max:50'],
        'sex' => ['nullable', 'string', 'max:20'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'home_address' => ['nullable', 'string', 'max:500'],
        'city' => ['nullable', 'string', 'max:255'],
        'postcode' => ['nullable', 'string', 'max:50'],
        'primary_role' => ['nullable', 'string', 'max:100'],
        'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
        'username' => ['required', 'string', 'max:255', 'unique:users,username'],
        'password' => ['required', 'string', 'min:8'],
        'mfa_enabled' => ['nullable', 'boolean'],
    ]);

    $fullName = trim(($payload['first_name'] ?? '').' '.($payload['surname'] ?? ''));

    $normalizedDateOfBirth = normalize_employee_date_of_birth($payload['date_of_birth'] ?? null);

    $employee = User::query()->create([
        'name' => $fullName,
        'email' => $payload['email'],
        'password' => $payload['password'],
        'title' => $payload['title'] ?? null,
        'first_name' => $payload['first_name'],
        'surname' => $payload['surname'],
        'date_of_birth' => $normalizedDateOfBirth,
        'sex' => $payload['sex'] ?? null,
        'username' => $payload['username'],
        'home_address' => $payload['home_address'] ?? null,
        'city' => $payload['city'] ?? null,
        'postcode' => $payload['postcode'] ?? null,
        'primary_role' => $payload['primary_role'] ?? null,
        'account_status' => 'active',
        'photo_path' => request()->hasFile('photo') ? request()->file('photo')->store('employee-photos', 'public') : null,
        'mfa_enabled' => array_key_exists('mfa_enabled', $payload) ? (bool) $payload['mfa_enabled'] : false,
        'email_verified_at' => now(),
    ]);

    AuditTrail::record(
        'created',
        'Enrolled staff member '.$fullName,
        'employee',
        (string) $employee->id,
        $fullName,
        [
            'email' => $payload['email'],
            'primary_role' => $payload['primary_role'] ?? null,
            'username' => $payload['username'],
        ],
    );

    FormSnapshot::query()->where('form_key', 'employee-create')->delete();

    return redirect()->route('employees')->with('success', 'Employee created successfully.');
})->middleware(['auth', 'verified', 'role:super_admin,admin'])->name('employees.store');

Route::get('/employees/{user}/profile', function (User $user) {
    $roleLabel = $user->primary_role
        ? Str::of($user->primary_role)->replace('_', ' ')->title()->toString()
        : 'Staff';

    return Inertia::render('EmployeeProfile', [
        'employee' => [
            'id' => $user->id,
            'title' => $user->title,
            'first_name' => $user->first_name,
            'surname' => $user->surname,
            'email' => $user->email,
            'phone' => $user->phone,
            'date_of_birth' => $user->date_of_birth,
            'sex' => $user->sex,
            'home_address' => $user->home_address,
            'city' => $user->city,
            'postcode' => $user->postcode,
            'primary_role' => $user->primary_role,
            'role_label' => $roleLabel,
            'account_status' => $user->account_status ?? 'active',
            'photoUrl' => $user->photo_path ? route('employees.photo', $user) : null,
            'dbs_certificate_number' => $user->dbs_certificate_number,
            'dbs_issue_date' => optional($user->dbs_issue_date)->format('Y-m-d'),
            'dbs_expiry_date' => optional($user->dbs_expiry_date)->format('Y-m-d'),
            'dbs_status' => $user->dbs_status,
        ],
        'trainingRecords' => $user->trainingRecords()
            ->orderByDesc('completed_date')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'course_name' => $r->course_name,
                'provider' => $r->provider,
                'completed_date' => optional($r->completed_date)->format('Y-m-d'),
                'expiry_date' => optional($r->expiry_date)->format('Y-m-d'),
                'certificate_reference' => $r->certificate_reference,
                'status' => $r->status,
            ]),
        'competencies' => $user->competencies()
            ->orderByDesc('assessed_date')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'skill_name' => $c->skill_name,
                'level' => $c->level,
                'assessed_date' => optional($c->assessed_date)->format('Y-m-d'),
                'next_review_date' => optional($c->next_review_date)->format('Y-m-d'),
                'assessed_by' => $c->assessed_by,
                'notes' => $c->notes,
                'status' => $c->status,
            ]),
        'supervisions' => $user->supervisions()
            ->orderByDesc('scheduled_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'scheduled_date' => optional($s->scheduled_date)->format('Y-m-d'),
                'completed_date' => optional($s->completed_date)->format('Y-m-d'),
                'next_due_date' => optional($s->next_due_date)->format('Y-m-d'),
                'notes' => $s->notes,
                'actions' => $s->actions,
                'status' => $s->status,
            ]),
        'documents' => $user->staffDocuments()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'category' => $d->category,
                'file_name' => $d->file_name,
                'expiry_date' => optional($d->expiry_date)->format('Y-m-d'),
                'created_at' => optional($d->created_at)->format('Y-m-d'),
            ]),
    ]);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('employees.profile');

Route::put('/employees/{user}', function (User $user) {
    $payload = request()->validate([
        'title' => ['nullable', 'string', 'max:50'],
        'first_name' => ['required', 'string', 'max:255'],
        'surname' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        'phone' => ['nullable', 'string', 'max:50'],
        'home_address' => ['nullable', 'string', 'max:500'],
        'city' => ['nullable', 'string', 'max:255'],
        'postcode' => ['nullable', 'string', 'max:50'],
        'primary_role' => ['nullable', 'string', 'max:100'],
        'date_of_birth' => ['nullable', 'string', 'max:50'],
        'sex' => ['nullable', 'string', 'max:20'],
        'dbs_certificate_number' => ['nullable', 'string', 'max:255'],
        'dbs_issue_date' => ['nullable', 'date'],
        'dbs_expiry_date' => ['nullable', 'date'],
        'dbs_status' => ['nullable', 'string', 'in:clear,pending,expired'],
    ]);

    $before = $user->only(array_keys($payload));
    $payload['name'] = trim(($payload['first_name'] ?? '').' '.($payload['surname'] ?? ''));
    $user->update($payload);

    AuditTrail::record(
        'updated',
        'Updated staff profile for '.($payload['name'] ?: 'Employee #'.$user->id),
        'employee',
        (string) $user->id,
        $payload['name'],
        ['before' => $before, 'after' => collect($payload)->except('name')->all()],
    );

    return redirect()->route('employees.profile', $user)->with('success', 'Profile updated successfully.');
})->middleware(['auth', 'verified', 'role:super_admin,care_manager'])->name('employees.update');

Route::post('/employees/{user}/training', function (User $user) {
    $payload = request()->validate([
        'course_name' => ['required', 'string', 'max:255'],
        'provider' => ['nullable', 'string', 'max:255'],
        'completed_date' => ['nullable', 'date'],
        'expiry_date' => ['nullable', 'date'],
        'certificate_reference' => ['nullable', 'string', 'max:255'],
        'status' => ['nullable', 'string', 'in:completed,pending,expired,expiring_soon'],
    ]);

    $user->trainingRecords()->create($payload);

    $employeeName = trim(($user->first_name ?? '').' '.($user->surname ?? ''));
    AuditTrail::record('created', "Added training '{$payload['course_name']}' for {$employeeName}", 'training', (string) $user->id, $employeeName);

    return redirect()->route('employees.profile', $user)->with('success', 'Training record added.');
})->middleware(['auth', 'verified', 'role:super_admin,care_manager'])->name('employees.training.store');

Route::post('/employees/{user}/competencies', function (User $user) {
    $payload = request()->validate([
        'skill_name' => ['required', 'string', 'max:255'],
        'level' => ['nullable', 'string', 'in:basic,intermediate,advanced,expert'],
        'assessed_date' => ['nullable', 'date'],
        'next_review_date' => ['nullable', 'date'],
        'assessed_by' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:2000'],
        'status' => ['nullable', 'string', 'in:competent,pending,not_competent'],
    ]);

    $user->competencies()->create($payload);

    $employeeName = trim(($user->first_name ?? '').' '.($user->surname ?? ''));
    AuditTrail::record('created', "Added competency '{$payload['skill_name']}' for {$employeeName}", 'competency', (string) $user->id, $employeeName);

    return redirect()->route('employees.profile', $user)->with('success', 'Competency record added.');
})->middleware(['auth', 'verified', 'role:super_admin,care_manager'])->name('employees.competencies.store');

Route::post('/employees/{user}/supervisions', function (User $user) {
    $payload = request()->validate([
        'scheduled_date' => ['required', 'date'],
        'completed_date' => ['nullable', 'date'],
        'next_due_date' => ['nullable', 'date'],
        'notes' => ['nullable', 'string', 'max:5000'],
        'actions' => ['nullable', 'string', 'max:5000'],
        'status' => ['nullable', 'string', 'in:scheduled,completed,overdue,cancelled'],
    ]);

    $payload['supervisor_id'] = request()->user()->id;
    $user->supervisions()->create($payload);

    $employeeName = trim(($user->first_name ?? '').' '.($user->surname ?? ''));
    AuditTrail::record('created', "Scheduled supervision for {$employeeName}", 'supervision', (string) $user->id, $employeeName);

    return redirect()->route('employees.profile', $user)->with('success', 'Supervision record added.');
})->middleware(['auth', 'verified', 'role:super_admin,care_manager'])->name('employees.supervisions.store');

Route::post('/employees/{user}/documents', function (User $user) {
    $payload = request()->validate([
        'title' => ['required', 'string', 'max:255'],
        'category' => ['nullable', 'string', 'max:100'],
        'expiry_date' => ['nullable', 'date'],
        'file' => ['required', 'file', 'max:10240'],
    ]);

    $file = request()->file('file');
    $path = $file->store("staff-documents/{$user->id}", 'public');

    $user->staffDocuments()->create([
        'title' => $payload['title'],
        'category' => $payload['category'] ?? null,
        'file_path' => $path,
        'file_name' => $file->getClientOriginalName(),
        'file_size' => $file->getSize(),
        'expiry_date' => $payload['expiry_date'] ?? null,
        'uploaded_by' => request()->user()->id,
    ]);

    $employeeName = trim(($user->first_name ?? '').' '.($user->surname ?? ''));
    AuditTrail::record('created', "Uploaded document '{$payload['title']}' for {$employeeName}", 'staff_document', (string) $user->id, $employeeName);

    return redirect()->route('employees.profile', $user)->with('success', 'Document uploaded.');
})->middleware(['auth', 'verified', 'role:super_admin,care_manager'])->name('employees.documents.store');

Route::get('/employees/{user}/documents/{document}/download', function (User $user, StaffDocument $document) {
    abort_unless($document->user_id === $user->id, 404);
    abort_unless(Storage::disk('public')->exists($document->file_path), 404);

    return Storage::disk('public')->download($document->file_path, $document->file_name);
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('employees.documents.download');

Route::post('/form-snapshots/{formKey}', function (string $formKey) {
    $payload = request()->validate([
        'data' => ['required', 'array'],
    ]);

    FormSnapshot::query()->updateOrCreate(
        ['form_key' => $formKey],
        [
            'data' => $payload['data'],
            'updated_by_user_id' => request()->user()?->id,
        ],
    );

    $patientUrlKey = null;
    if (str_contains($formKey, ':')) {
        [, $patientUrlKey] = explode(':', $formKey, 2);
    }

    $isIncident = str_starts_with($formKey, 'incident:');
    $isSubmitted = ($payload['data']['status'] ?? null) === 'Submitted';

    if ($isIncident && $isSubmitted && $patientUrlKey) {
        $patient = Patient::query()->where('url_key', $patientUrlKey)->first();
        if ($patient) {
            $incident = record_patient_incident_submission($patient, $payload['data'], request()->user());
            FormSnapshot::query()->where('form_key', $formKey)->update([
                'data' => ['status' => 'Draft'],
            ]);
            AuditTrail::record(
                'created',
                'Incident '.$incident->reference.' submitted for '.$patient->name,
                'incident',
                (string) $incident->id,
                $patient->name,
                ['reference' => $incident->reference],
                ['patient_url_key' => $patientUrlKey],
            );

            $flash = ['success' => 'Incident submitted as '.$incident->reference.'.'];
            if (incident_involves_personal_data($payload['data'])) {
                $flash['suggest_gdpr_breach'] = true;
                $flash['gdprBreachPrefill'] = [
                    'patient_id' => $patient->id,
                    'subject_name' => $patient->name,
                    'request_details' => 'Potential personal data breach linked to incident '.$incident->reference
                        .'. Review whether notification to the ICO or individuals is required within 72 hours.',
                    'breach_categories' => 'Incident-related',
                    'discovered_at' => now()->format('Y-m-d\TH:i'),
                    'incident_reference' => $incident->reference,
                    'incident_id' => $incident->id,
                ];
            }

            return redirect()
                ->route('patients.incidents.create', $patientUrlKey)
                ->with($flash);
        }
    }

    $isShiftCheckin = str_starts_with($formKey, 'shift-checkin:');

    if ($isIncident && $patientUrlKey) {
        $patientName = Patient::query()->where('url_key', $patientUrlKey)->value('name') ?? $patientUrlKey;
        $description = $isSubmitted
            ? "Incident reported for {$patientName}"
            : "Incident draft saved for {$patientName}";
        $subjectLabel = $patientName;
    } elseif ($isShiftCheckin && $patientUrlKey) {
        $patientName = Patient::query()->where('url_key', $patientUrlKey)->value('name') ?? $patientUrlKey;
        $description = "Shift check-in recorded for {$patientName}";
        $subjectLabel = $patientName;
    } else {
        $patientName = $patientUrlKey ? (Patient::query()->where('url_key', $patientUrlKey)->value('name') ?? $patientUrlKey) : null;
        $description = $patientName ? "Form updated for {$patientName}" : 'Form updated: '.$formKey;
        $subjectLabel = $patientName ?? $formKey;
    }

    $auditAction = $isIncident && $isSubmitted ? 'created' : ($isShiftCheckin ? 'shift_checkin' : 'saved');
    $auditSubjectType = $isIncident ? 'incident' : ($isShiftCheckin ? 'shift_checkin' : 'form_snapshot');

    AuditTrail::record(
        $auditAction,
        $description,
        $auditSubjectType,
        $patientUrlKey ?? $formKey,
        $subjectLabel,
        null,
        $patientUrlKey ? ['patient_url_key' => $patientUrlKey] : null,
    );

    return redirect()->back();
})->middleware(['auth', 'verified'])->name('form-snapshots.save');

Route::get('/team', function () {
    return redirect()->route('employees');
})->middleware(['auth', 'verified'])->name('team');

Route::get('/api/medication-reminders', function () {
    $user = request()->user();
    if (!$user) {
        return response()->json([]);
    }

    $assignedPatientIds = PatientSchedule::query()
        ->where('assigned_user_id', $user->id)
        ->whereDate('start_at', '<=', now())
        ->whereDate('end_at', '>=', now())
        ->pluck('patient_id')
        ->unique();

    if ($assignedPatientIds->isEmpty()) {
        $assignedPatientIds = Patient::query()->pluck('id');
    }

    $reminders = MedicationReminder::query()
        ->whereIn('patient_id', $assignedPatientIds)
        ->where('dismissed', false)
        ->whereDate('due_at', now()->toDateString())
        ->with(['patient:id,name,url_key', 'medication:id,name,dose,route'])
        ->orderBy('due_at')
        ->get()
        ->map(fn ($r) => [
            'id' => $r->id,
            'patient_name' => $r->patient?->name ?? '-',
            'patient_url_key' => $r->patient?->url_key ?? '-',
            'medication_name' => $r->medication?->name ?? '-',
            'dose' => $r->medication?->dose ?? '-',
            'route' => $r->medication?->route ?? '-',
            'due_at' => $r->due_at->format('H:i'),
            'is_overdue' => $r->due_at->isPast(),
        ]);

    return response()->json($reminders);
})->middleware(['auth', 'verified'])->name('api.medication-reminders');

Route::post('/api/medication-reminders/{id}/dismiss', function (int $id) {
    $reminder = MedicationReminder::query()->findOrFail($id);
    $reminder->update([
        'dismissed' => true,
        'dismissed_by_user_id' => request()->user()?->id,
    ]);
    return response()->json(['ok' => true]);
})->middleware(['auth', 'verified'])->name('api.medication-reminders.dismiss');

Route::get('/reports/medications', function () {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $administrations = MedicationAdministration::query()
        ->whereBetween('created_at', [$from, $to])
        ->with(['medication:id,name,is_controlled,is_prn', 'patient:id,name,url_key', 'administeredBy:id,name'])
        ->orderByDesc('created_at')
        ->get();

    $totalAdmin = $administrations->count();
    $givenCount = $administrations->where('status', 'given')->count();
    $refusedCount = $administrations->where('status', 'refused')->count();
    $omittedCount = $administrations->where('status', 'omitted')->count();
    $delayedCount = $administrations->where('status', 'delayed')->count();
    $selfAdminCount = $administrations->where('status', 'self_administered')->count();
    $controlledCount = $administrations->filter(fn ($a) => $a->medication?->is_controlled)->count();
    $scheduledCount = $administrations->whereNotNull('scheduled_for')->count();
    $complianceRate = $scheduledCount > 0 ? round(($givenCount / $scheduledCount) * 100, 1) : 0;

    $refusedReasons = $administrations
        ->where('status', 'refused')
        ->whereNotNull('reason')
        ->groupBy('reason')
        ->map->count()
        ->sortByDesc(fn ($v) => $v)
        ->take(10);

    $byPatient = $administrations->groupBy(fn ($a) => $a->patient?->name ?? 'Unknown')->map(function ($group) {
        return [
            'total' => $group->count(),
            'given' => $group->where('status', 'given')->count(),
            'refused' => $group->where('status', 'refused')->count(),
            'omitted' => $group->where('status', 'omitted')->count(),
        ];
    })->sortByDesc(fn ($v) => $v['total'])->take(20);

    $recentRows = $administrations->take(100)->map(fn ($a) => [
        'id' => $a->id,
        'patient' => $a->patient?->name ?? '-',
        'medication' => $a->medication?->name ?? '-',
        'status' => $a->status,
        'administered_by' => $a->administeredBy?->name ?? '-',
        'administered_at' => $a->administered_at?->format('d M Y H:i') ?? '-',
        'reason' => $a->reason,
        'witness' => $a->witness_name,
        'is_controlled' => $a->medication?->is_controlled ?? false,
        'is_prn' => $a->medication?->is_prn ?? false,
    ])->values();

    return Inertia::render('ReportsMedications', [
        'stats' => [
            'totalAdministrations' => $totalAdmin,
            'given' => $givenCount,
            'refused' => $refusedCount,
            'omitted' => $omittedCount,
            'delayed' => $delayedCount,
            'selfAdministered' => $selfAdminCount,
            'controlled' => $controlledCount,
            'complianceRate' => $complianceRate,
        ],
        'refusedReasons' => $refusedReasons,
        'byPatient' => $byPatient,
        'administrations' => $recentRows,
        'filters' => [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('reports.medications');

Route::get('/reports/medications/export/csv', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $administrations = MedicationAdministration::query()
        ->whereBetween('created_at', [$from, $to])
        ->with(['medication:id,name,is_controlled,is_prn', 'patient:id,name,url_key', 'administeredBy:id,name'])
        ->orderByDesc('created_at')
        ->get();

    $filename = 'Allocare-medication-report-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($administrations): void {
        $output = fopen('php://output', 'w');
        if (! $output) {
            return;
        }

        fputcsv($output, ['Patient', 'Medication', 'Status', 'Administered By', 'Administered At', 'Reason', 'Witness', 'Controlled', 'PRN']);
        foreach ($administrations as $a) {
            fputcsv($output, [
                $a->patient?->name ?? '-',
                $a->medication?->name ?? '-',
                $a->status,
                $a->administeredBy?->name ?? '-',
                $a->administered_at?->format('d M Y H:i') ?? '-',
                $a->reason ?? '',
                $a->witness_name ?? '',
                $a->medication?->is_controlled ? 'Yes' : 'No',
                $a->medication?->is_prn ? 'Yes' : 'No',
            ]);
        }
        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.medications.export.csv');

Route::get('/reports/medications/export/pdf', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $administrations = MedicationAdministration::query()
        ->whereBetween('created_at', [$from, $to])
        ->with(['medication:id,name,is_controlled,is_prn', 'patient:id,name,url_key', 'administeredBy:id,name'])
        ->orderByDesc('created_at')
        ->limit(250)
        ->get();

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.medications-pdf', [
        'from' => $from,
        'to' => $to,
        'administrations' => $administrations,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('Allocare-medication-report-'.now()->format('Ymd-His').'.pdf');
})->middleware(['auth', 'verified'])->name('reports.medications.export.pdf');

Route::get('/reports/schedules', function () {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $schedules = PatientSchedule::query()
        ->whereBetween('start_at', [$from, $to])
        ->with(['patient:id,name,url_key', 'assignedUser:id,name'])
        ->orderByDesc('start_at')
        ->get();

    $totalShifts = $schedules->count();
    $completedShifts = $schedules->filter(fn ($s) => $s->status === 'completed')->count();
    $missedShifts = $schedules->filter(fn ($s) => $s->status === 'missed')->count();
    $upcomingShifts = $schedules->filter(fn ($s) => $s->start_at->isFuture() && !$s->status)->count();
    $overdueShifts = $schedules->filter(fn ($s) => $s->end_at->isPast() && !$s->status)->count();
    $inProgressShifts = $totalShifts - $completedShifts - $missedShifts - $upcomingShifts - $overdueShifts;
    $lateStarts = $schedules->filter(fn ($s) => (int) ($s->late_by_minutes ?? 0) > 0)->count();
    $earlyLeaves = $schedules->filter(fn ($s) => (int) ($s->left_early_by_minutes ?? 0) > 0)->count();

    $rescheduledCount = AuditEvent::query()
        ->where('subject_type', 'schedule')
        ->where('action', 'updated')
        ->whereBetween('created_at', [$from, $to])
        ->count();

    $totalHours = $schedules->sum(function ($s) {
        return $s->start_at->diffInMinutes($s->end_at) / 60;
    });

    $byStaff = $schedules->groupBy('assigned_user_id')->map(function ($group) {
        $user = $group->first()->assignedUser;
        $hours = $group->sum(fn ($s) => $s->start_at->diffInMinutes($s->end_at) / 60);
        return [
            'name' => $user?->name ?? 'Unassigned',
            'shifts' => $group->count(),
            'hours' => round($hours, 1),
        ];
    })->sortByDesc('shifts')->values();

    $recentShifts = $schedules->map(function ($s) {
        if ($s->status === 'completed') {
            $displayStatus = 'Completed';
        } elseif ($s->status === 'missed') {
            $displayStatus = 'Missed';
        } elseif ($s->start_at->isFuture()) {
            $displayStatus = 'Upcoming';
        } elseif ($s->end_at->isPast()) {
            $displayStatus = 'Overdue';
        } else {
            $displayStatus = 'In Progress';
        }

        return [
            'id' => $s->id,
            'patient' => $s->patient?->name ?? '-',
            'carer' => $s->assignedUser?->name ?? '-',
            'date' => $s->start_at->format('d M Y'),
            'time' => $s->start_at->format('H:i').' - '.$s->end_at->format('H:i'),
            'duration' => $s->start_at->diffInMinutes($s->end_at),
            'status' => $displayStatus,
            'hasEcmData' => $s->checked_in_at !== null
                || $s->checked_out_at !== null
                || $s->check_in_latitude !== null
                || $s->check_in_longitude !== null
                || $s->check_out_latitude !== null
                || $s->check_out_longitude !== null
                || $s->check_in_distance_metres !== null
                || $s->check_out_distance_metres !== null
                || $s->late_by_minutes !== null
                || $s->left_early_by_minutes !== null,
            'lateByMinutes' => (int) ($s->late_by_minutes ?? 0),
            'leftEarlyByMinutes' => (int) ($s->left_early_by_minutes ?? 0),
        ];
    })->values();

    $byPatient = $schedules->groupBy(fn ($s) => $s->patient?->name ?? 'Unknown')->map(fn ($group) => $group->count())->sortDesc()->all();

    return Inertia::render('ReportsSchedules', [
        'stats' => [
            'totalShifts' => $totalShifts,
            'completedShifts' => $completedShifts,
            'missedShifts' => $missedShifts,
            'upcomingShifts' => $upcomingShifts,
            'inProgressShifts' => $inProgressShifts,
            'overdueShifts' => $overdueShifts,
            'lateStarts' => $lateStarts,
            'earlyLeaves' => $earlyLeaves,
            'rescheduledShifts' => $rescheduledCount,
            'totalHours' => round($totalHours, 1),
        ],
        'byStaff' => $byStaff,
        'byPatient' => $byPatient,
        'shifts' => $recentShifts,
        'filters' => [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('reports.schedules');

Route::get('/reports/schedules/export/csv', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $schedules = PatientSchedule::query()
        ->whereBetween('start_at', [$from, $to])
        ->with(['patient:id,name,url_key', 'assignedUser:id,name'])
        ->orderByDesc('start_at')
        ->get();

    $filename = 'Allocare-schedule-report-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($schedules): void {
        $output = fopen('php://output', 'w');
        if (! $output) {
            return;
        }

        fputcsv($output, ['Date', 'Start', 'End', 'Patient', 'Carer', 'Duration (mins)', 'Status', 'ECM', 'Late (mins)', 'Left Early (mins)']);
        foreach ($schedules as $s) {
            $hasEcmData = $s->checked_in_at !== null
                || $s->checked_out_at !== null
                || $s->check_in_latitude !== null
                || $s->check_in_longitude !== null
                || $s->check_out_latitude !== null
                || $s->check_out_longitude !== null
                || $s->check_in_distance_metres !== null
                || $s->check_out_distance_metres !== null
                || $s->late_by_minutes !== null
                || $s->left_early_by_minutes !== null;
            $lateBy = (int) ($s->late_by_minutes ?? 0);
            $leftEarlyBy = (int) ($s->left_early_by_minutes ?? 0);
            $ecmLabel = !$hasEcmData
                ? 'No ECM data'
                : (($lateBy > 0 || $leftEarlyBy > 0)
                    ? trim(($lateBy > 0 ? "Late {$lateBy}m " : '').($leftEarlyBy > 0 ? "Early {$leftEarlyBy}m" : ''))
                    : 'On time');

            fputcsv($output, [
                $s->start_at?->format('d M Y'),
                $s->start_at?->format('H:i'),
                $s->end_at?->format('H:i'),
                $s->patient?->name ?? '-',
                $s->assignedUser?->name ?? '-',
                $s->start_at && $s->end_at ? $s->start_at->diffInMinutes($s->end_at) : 0,
                $s->status ?? 'in_progress',
                $ecmLabel,
                $lateBy,
                $leftEarlyBy,
            ]);
        }
        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.schedules.export.csv');

Route::get('/reports/schedules/export/pdf', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $schedules = PatientSchedule::query()
        ->whereBetween('start_at', [$from, $to])
        ->with(['patient:id,name,url_key', 'assignedUser:id,name'])
        ->orderByDesc('start_at')
        ->limit(250)
        ->get();

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.schedules-pdf', [
        'from' => $from,
        'to' => $to,
        'schedules' => $schedules,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('Allocare-schedule-report-'.now()->format('Ymd-His').'.pdf');
})->middleware(['auth', 'verified'])->name('reports.schedules.export.pdf');

Route::get('/reports/ecm-commissioner', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $schedules = PatientSchedule::query()
        ->whereBetween('start_at', [$from, $to])
        ->where(function ($query) {
            $query
                ->whereNotNull('checked_in_at')
                ->orWhereNotNull('checked_out_at')
                ->orWhereNotNull('check_in_distance_metres')
                ->orWhereNotNull('check_out_distance_metres')
                ->orWhereNotNull('late_by_minutes')
                ->orWhereNotNull('left_early_by_minutes');
        })
        ->with(['patient:id,name', 'assignedUser:id,name'])
        ->orderByDesc('start_at')
        ->get();

    $rows = $schedules->map(function (PatientSchedule $schedule) {
        $scheduledMinutes = ($schedule->start_at && $schedule->end_at)
            ? $schedule->start_at->diffInMinutes($schedule->end_at)
            : 0;
        $actualMinutes = ($schedule->checked_in_at && $schedule->checked_out_at)
            ? $schedule->checked_in_at->diffInMinutes($schedule->checked_out_at)
            : null;
        $evidenceStatus = ($schedule->checked_in_at && $schedule->checked_out_at)
            ? 'Complete'
            : (($schedule->checked_in_at || $schedule->checked_out_at) ? 'Partial' : 'Missing');

        return [
            'id' => $schedule->id,
            'scheduledDate' => optional($schedule->start_at)->format('d M Y'),
            'scheduledWindow' => optional($schedule->start_at)->format('H:i').' - '.optional($schedule->end_at)->format('H:i'),
            'patient' => $schedule->patient?->name ?? '-',
            'carer' => $schedule->assignedUser?->name ?? '-',
            'checkedInAt' => optional($schedule->checked_in_at)->format('d M Y H:i'),
            'checkedOutAt' => optional($schedule->checked_out_at)->format('d M Y H:i'),
            'scheduledMinutes' => $scheduledMinutes,
            'actualMinutes' => $actualMinutes,
            'lateByMinutes' => (int) ($schedule->late_by_minutes ?? 0),
            'leftEarlyByMinutes' => (int) ($schedule->left_early_by_minutes ?? 0),
            'checkInDistanceMetres' => $schedule->check_in_distance_metres,
            'checkOutDistanceMetres' => $schedule->check_out_distance_metres,
            'checkInCoords' => ($schedule->check_in_latitude !== null && $schedule->check_in_longitude !== null)
                ? number_format((float) $schedule->check_in_latitude, 6).', '.number_format((float) $schedule->check_in_longitude, 6)
                : null,
            'checkOutCoords' => ($schedule->check_out_latitude !== null && $schedule->check_out_longitude !== null)
                ? number_format((float) $schedule->check_out_latitude, 6).', '.number_format((float) $schedule->check_out_longitude, 6)
                : null,
            'evidenceStatus' => $evidenceStatus,
        ];
    })->values();

    return Inertia::render('ReportsEcmCommissioner', [
        'rows' => $rows,
        'stats' => [
            'totalRows' => $rows->count(),
            'completeEvidence' => $rows->where('evidenceStatus', 'Complete')->count(),
            'partialEvidence' => $rows->where('evidenceStatus', 'Partial')->count(),
            'missingEvidence' => $rows->where('evidenceStatus', 'Missing')->count(),
        ],
        'filters' => [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('reports.ecm-commissioner');

Route::get('/reports/ecm-commissioner/export/csv', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $fromParam = request('from');
    $toParam = request('to');
    $from = $fromParam ? Carbon::parse($fromParam)->startOfDay() : now()->subDays(30)->startOfDay();
    $to = $toParam ? Carbon::parse($toParam)->endOfDay() : now()->endOfDay();

    $schedules = PatientSchedule::query()
        ->whereBetween('start_at', [$from, $to])
        ->where(function ($query) {
            $query
                ->whereNotNull('checked_in_at')
                ->orWhereNotNull('checked_out_at')
                ->orWhereNotNull('check_in_distance_metres')
                ->orWhereNotNull('check_out_distance_metres')
                ->orWhereNotNull('late_by_minutes')
                ->orWhereNotNull('left_early_by_minutes');
        })
        ->with(['patient:id,name', 'assignedUser:id,name'])
        ->orderByDesc('start_at')
        ->get();

    $filename = 'Allocare-ecm-commissioner-attendance-evidence-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($schedules): void {
        $output = fopen('php://output', 'w');
        if (! $output) {
            return;
        }

        fputcsv($output, [
            'Shift ID',
            'Scheduled Date',
            'Scheduled Start',
            'Scheduled End',
            'Patient',
            'Carer',
            'Check-In At',
            'Check-Out At',
            'Late (mins)',
            'Left Early (mins)',
            'Check-In Distance (m)',
            'Check-Out Distance (m)',
            'Check-In Coordinates',
            'Check-Out Coordinates',
            'Evidence Status',
        ]);

        foreach ($schedules as $schedule) {
            $evidenceStatus = ($schedule->checked_in_at && $schedule->checked_out_at)
                ? 'Complete'
                : (($schedule->checked_in_at || $schedule->checked_out_at) ? 'Partial' : 'Missing');

            fputcsv($output, [
                $schedule->id,
                $schedule->start_at?->format('d M Y'),
                $schedule->start_at?->format('H:i'),
                $schedule->end_at?->format('H:i'),
                $schedule->patient?->name ?? '-',
                $schedule->assignedUser?->name ?? '-',
                $schedule->checked_in_at?->format('d M Y H:i'),
                $schedule->checked_out_at?->format('d M Y H:i'),
                $schedule->late_by_minutes ?? 0,
                $schedule->left_early_by_minutes ?? 0,
                $schedule->check_in_distance_metres ?? '',
                $schedule->check_out_distance_metres ?? '',
                ($schedule->check_in_latitude !== null && $schedule->check_in_longitude !== null)
                    ? number_format((float) $schedule->check_in_latitude, 6).', '.number_format((float) $schedule->check_in_longitude, 6)
                    : '',
                ($schedule->check_out_latitude !== null && $schedule->check_out_longitude !== null)
                    ? number_format((float) $schedule->check_out_latitude, 6).', '.number_format((float) $schedule->check_out_longitude, 6)
                    : '',
                $evidenceStatus,
            ]);
        }

        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.ecm-commissioner.export.csv');

if (! function_exists('build_compliance_training_report_payload')) {
    function build_compliance_training_report_payload(): array
    {
        $selectedRole = strtolower(trim((string) request('role', 'all')));
        $selectedRisk = strtolower(trim((string) request('risk', 'all')));
        $today = Carbon::today();
        $now = Carbon::now();

        $users = User::query()
            ->with([
                'trainingRecords:id,user_id,expiry_date,status',
                'competencies:id,user_id,next_review_date,status',
                'supervisions:id,user_id,next_due_date,status,scheduled_date,completed_date',
                'staffDocuments:id,user_id,expiry_date,category',
            ])
            ->get();

        $staffRows = $users->map(function (User $user) use ($today) {
            $staffName = trim((string) ($user->name ?: (($user->first_name ?? '').' '.($user->surname ?? ''))));
            $staffName = $staffName !== '' ? $staffName : 'Staff #'.$user->id;

            $roleValue = strtolower(trim((string) ($user->primary_role ?? '')));
            $roleLabel = $roleValue !== '' ? Str::of($roleValue)->replace('_', ' ')->title()->toString() : 'Unassigned';

            $trainingRecords = $user->trainingRecords;
            $trainingCount = $trainingRecords->count();
            $trainingExpired = $trainingRecords->filter(fn ($record) => $record->expiry_date && $record->expiry_date->lt($today))->count();
            $trainingDueSoon = $trainingRecords->filter(fn ($record) => $record->expiry_date && $record->expiry_date->betweenIncluded($today, $today->copy()->addDays(30)))->count();

            $competencies = $user->competencies;
            $competencyCount = $competencies->count();
            $competencyOverdue = $competencies->filter(fn ($row) => $row->next_review_date && $row->next_review_date->lt($today))->count();
            $competencyDueSoon = $competencies->filter(fn ($row) => $row->next_review_date && $row->next_review_date->betweenIncluded($today, $today->copy()->addDays(30)))->count();

            $supervisions = $user->supervisions;
            $supervisionCount = $supervisions->count();
            $supervisionOverdue = $supervisions
                ->filter(fn ($row) => ($row->status === 'overdue') || ($row->next_due_date && $row->next_due_date->lt($today)))
                ->count();
            $supervisionDueSoon = $supervisions
                ->filter(fn ($row) => $row->next_due_date && $row->next_due_date->betweenIncluded($today, $today->copy()->addDays(30)))
                ->count();

            $documents = $user->staffDocuments;
            $documentCount = $documents->count();
            $documentExpired = $documents->filter(fn ($row) => $row->expiry_date && $row->expiry_date->lt($today))->count();
            $documentDueSoon = $documents->filter(fn ($row) => $row->expiry_date && $row->expiry_date->betweenIncluded($today, $today->copy()->addDays(30)))->count();

            $dbsExpiryDate = $user->dbs_expiry_date ? Carbon::parse($user->dbs_expiry_date) : null;
            $dbsMissing = $dbsExpiryDate === null;
            $dbsExpired = $dbsExpiryDate !== null && $dbsExpiryDate->lt($today);
            $dbsDueSoon = $dbsExpiryDate !== null && $dbsExpiryDate->betweenIncluded($today, $today->copy()->addDays(30));

            $missingEvidenceCount = 0;
            if ($trainingCount === 0) {
                $missingEvidenceCount++;
            }
            if ($competencyCount === 0) {
                $missingEvidenceCount++;
            }
            if ($supervisionCount === 0) {
                $missingEvidenceCount++;
            }
            if ($documentCount === 0) {
                $missingEvidenceCount++;
            }
            if ($dbsMissing) {
                $missingEvidenceCount++;
            }

            $criticalOverdueCount = $trainingExpired + $competencyOverdue + $supervisionOverdue + $documentExpired + ($dbsExpired ? 1 : 0);
            $dueSoonCount = $trainingDueSoon + $competencyDueSoon + $supervisionDueSoon + $documentDueSoon + ($dbsDueSoon ? 1 : 0);

            $risk = 'green';
            if ($criticalOverdueCount > 0 || $dbsMissing) {
                $risk = 'red';
            } elseif ($dueSoonCount > 0 || $missingEvidenceCount > 0) {
                $risk = 'amber';
            }

            $expiringDates = collect([
                ...$trainingRecords->pluck('expiry_date')->filter()->all(),
                ...$competencies->pluck('next_review_date')->filter()->all(),
                ...$supervisions->pluck('next_due_date')->filter()->all(),
                ...$documents->pluck('expiry_date')->filter()->all(),
                $dbsExpiryDate,
            ])->filter();

            $nextDueDate = $expiringDates
                ->map(fn ($date) => Carbon::parse($date))
                ->filter(fn (Carbon $date) => $date->gte($today))
                ->sort()
                ->first();

            $actions = collect();
            if ($dbsMissing) {
                $actions->push(['message' => 'Upload missing DBS evidence', 'due_date' => null]);
            } elseif ($dbsExpired) {
                $actions->push(['message' => 'Renew expired DBS check', 'due_date' => $dbsExpiryDate?->toDateString()]);
            } elseif ($dbsDueSoon) {
                $actions->push(['message' => 'Plan DBS renewal', 'due_date' => $dbsExpiryDate?->toDateString()]);
            }
            if ($trainingExpired > 0) {
                $actions->push(['message' => "Complete {$trainingExpired} expired training item(s)", 'due_date' => $nextDueDate?->toDateString()]);
            }
            if ($supervisionOverdue > 0) {
                $actions->push(['message' => "Schedule {$supervisionOverdue} overdue supervision(s)", 'due_date' => $nextDueDate?->toDateString()]);
            }
            if ($documentExpired > 0) {
                $actions->push(['message' => "Replace {$documentExpired} expired document(s)", 'due_date' => $nextDueDate?->toDateString()]);
            }
            if ($actions->isEmpty() && $dueSoonCount > 0) {
                $actions->push(['message' => "Review {$dueSoonCount} item(s) due in 30 days", 'due_date' => $nextDueDate?->toDateString()]);
            }
            if ($actions->isEmpty() && $missingEvidenceCount > 0) {
                $actions->push(['message' => 'Upload missing compliance evidence', 'due_date' => null]);
            }

            return [
                'id' => $user->id,
                'staff_name' => $staffName,
                'role_value' => $roleValue !== '' ? $roleValue : 'unassigned',
                'role_label' => $roleLabel,
                'risk' => $risk,
                'critical_overdue_count' => $criticalOverdueCount,
                'due_soon_count' => $dueSoonCount,
                'missing_evidence_count' => $missingEvidenceCount,
                'training_summary' => "{$trainingCount} total / {$trainingExpired} overdue / {$trainingDueSoon} due soon",
                'competency_summary' => "{$competencyCount} total / {$competencyOverdue} overdue / {$competencyDueSoon} due soon",
                'supervision_summary' => "{$supervisionCount} total / {$supervisionOverdue} overdue / {$supervisionDueSoon} due soon",
                'dbs_summary' => $dbsMissing
                    ? 'Missing evidence'
                    : ($dbsExpired ? 'Expired' : ($dbsDueSoon ? 'Due soon' : 'Valid')),
                'documents_summary' => "{$documentCount} total / {$documentExpired} expired / {$documentDueSoon} due soon",
                'actions' => $actions->values()->all(),
                'expiring_dates' => $expiringDates
                    ->map(fn ($date) => Carbon::parse($date)->toDateString())
                    ->values()
                    ->all(),
            ];
        });

        if ($selectedRole !== 'all') {
            $staffRows = $staffRows->where('role_value', $selectedRole)->values();
        }
        if (in_array($selectedRisk, ['green', 'amber', 'red'], true)) {
            $staffRows = $staffRows->where('risk', $selectedRisk)->values();
        }

        $totalStaff = $staffRows->count();
        $fullyCompliant = $staffRows->where('risk', 'green')->count();
        $dueSoon = $staffRows->where('risk', 'amber')->count();
        $overdueCritical = $staffRows->where('risk', 'red')->count();
        $missingEvidence = $staffRows->sum('missing_evidence_count');
        $complianceRate = $totalStaff > 0 ? round(($fullyCompliant / $totalStaff) * 100, 1) : 0;

        $allExpiringDates = $staffRows->flatMap(fn ($row) => $row['expiring_dates'] ?? []);
        $expiryWindows = [
            'days7' => $allExpiringDates->filter(function ($rawDate) use ($now) {
                $days = $now->diffInDays(Carbon::parse($rawDate), false);
                return $days >= 0 && $days <= 7;
            })->count(),
            'days30' => $allExpiringDates->filter(function ($rawDate) use ($now) {
                $days = $now->diffInDays(Carbon::parse($rawDate), false);
                return $days >= 0 && $days <= 30;
            })->count(),
            'days60' => $allExpiringDates->filter(function ($rawDate) use ($now) {
                $days = $now->diffInDays(Carbon::parse($rawDate), false);
                return $days >= 0 && $days <= 60;
            })->count(),
            'days90' => $allExpiringDates->filter(function ($rawDate) use ($now) {
                $days = $now->diffInDays(Carbon::parse($rawDate), false);
                return $days >= 0 && $days <= 90;
            })->count(),
        ];

        $actions = $staffRows
            ->flatMap(function ($row) {
                return collect($row['actions'])->map(fn ($action) => [
                    'user_id' => $row['id'],
                    'staff_name' => $row['staff_name'],
                    'role_label' => $row['role_label'],
                    'risk' => $row['risk'],
                    'message' => $action['message'],
                    'due_date' => $action['due_date'],
                ]);
            })
            ->sortBy([
                fn ($item) => $item['risk'] === 'red' ? 0 : ($item['risk'] === 'amber' ? 1 : 2),
                fn ($item) => $item['due_date'] ?? '9999-12-31',
            ])
            ->take(50)
            ->values();

        $roleOptions = $users
            ->map(fn (User $user) => strtolower(trim((string) ($user->primary_role ?? ''))))
            ->filter(fn ($role) => $role !== '')
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($role) => [
                'value' => $role,
                'label' => Str::of($role)->replace('_', ' ')->title()->toString(),
            ]);

        return [
            'stats' => [
                'totalStaff' => $totalStaff,
                'fullyCompliant' => $fullyCompliant,
                'dueSoon' => $dueSoon,
                'overdueCritical' => $overdueCritical,
                'complianceRate' => $complianceRate,
                'missingEvidence' => $missingEvidence,
            ],
            'riskSummary' => [
                'green' => $fullyCompliant,
                'amber' => $dueSoon,
                'red' => $overdueCritical,
            ],
            'expiryWindows' => $expiryWindows,
            'staffRows' => $staffRows
                ->map(fn ($row) => collect($row)->except(['actions', 'expiring_dates'])->all())
                ->values(),
            'actions' => $actions,
            'filters' => [
                'role' => $selectedRole,
                'risk' => $selectedRisk,
            ],
            'roleOptions' => $roleOptions,
        ];
    }
}

Route::get('/reports/compliance-training', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    return Inertia::render('ReportsComplianceTraining', build_compliance_training_report_payload());
})->middleware(['auth', 'verified'])->name('reports.compliance-training');

Route::get('/reports/compliance-training/export/csv', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $payload = build_compliance_training_report_payload();
    $filename = 'Allocare-compliance-training-report-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($payload): void {
        $output = fopen('php://output', 'w');
        if (! $output) {
            return;
        }

        fputcsv($output, ['Staff Name', 'Role', 'Risk', 'Training', 'Competencies', 'Supervisions', 'DBS', 'Documents']);
        foreach ($payload['staffRows'] as $row) {
            $risk = strtolower((string) ($row['risk'] ?? ''));
            $riskLabel = match ($risk) {
                'red' => 'High',
                'amber' => 'Medium',
                default => 'Low',
            };

            fputcsv($output, [
                $row['staff_name'],
                $row['role_label'],
                $riskLabel,
                $row['training_summary'],
                $row['competency_summary'],
                $row['supervision_summary'],
                $row['dbs_summary'],
                $row['documents_summary'],
            ]);
        }
        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.compliance-training.export.csv');

Route::get('/reports/compliance-training/export/pdf', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $payload = build_compliance_training_report_payload();
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.compliance-training-pdf', $payload)->setPaper('a4', 'landscape');

    return $pdf->download('Allocare-compliance-training-report-'.now()->format('Ymd-His').'.pdf');
})->middleware(['auth', 'verified'])->name('reports.compliance-training.export.pdf');

Route::get('/reports/incidents', function () {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $incidents = PatientIncident::query()
        ->with(['patient:id,name,url_key', 'reportedBy:id,name', 'investigation.incident'])
        ->orderByDesc('submitted_at')
        ->limit(500)
        ->get()
        ->map(fn (PatientIncident $incident) => map_patient_incident_for_list($incident))
        ->values();

    $totalIncidents = $incidents->count();
    $openInvestigations = $incidents->filter(fn ($row) => $row['investigation']
        && !in_array($row['investigation']['status'], ['completed', 'closed'], true))->count();
    $riddorOpen = $incidents->filter(fn ($row) => ($row['investigation']['riddorReportable'] ?? false)
        && !($row['investigation']['riddorReportedAt'] ?? null))->count();
    $safeguardingOpen = $incidents->filter(fn ($row) => ($row['investigation']['safeguardingConcern'] ?? false)
        && !($row['investigation']['safeguardingReferralMade'] ?? false))->count();
    $byPatient = $incidents->groupBy('patient_name')->map->count()->sortByDesc(fn ($v) => $v);

    return Inertia::render('ReportsIncidents', [
        'incidents' => $incidents,
        'stats' => [
            'total' => $totalIncidents,
            'submitted' => $totalIncidents,
            'drafts' => 0,
            'openInvestigations' => $openInvestigations,
            'riddorOpen' => $riddorOpen,
            'safeguardingOpen' => $safeguardingOpen,
            'byPatient' => $byPatient,
        ],
        'investigationStatusOptions' => IncidentInvestigation::STATUSES,
        'riddorCategoryOptions' => collect(IncidentInvestigation::RIDDOR_CATEGORIES)
            ->map(fn (string $cat) => [
                'value' => $cat,
                'label' => Str::of($cat)->replace('_', ' ')->title()->toString(),
            ])
            ->values(),
    ]);
})->middleware(['auth', 'verified'])->name('reports.incidents');

Route::get('/reports/incidents/export/csv', function () {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $incidents = PatientIncident::query()
        ->with(['patient:id,name', 'reportedBy:id,name', 'investigation'])
        ->orderByDesc('submitted_at')
        ->get()
        ->map(fn (PatientIncident $incident) => map_patient_incident_for_list($incident));

    $filename = 'Allocare-incident-report-'.now()->format('Ymd-His').'.csv';
    return response()->streamDownload(function () use ($incidents): void {
        $output = fopen('php://output', 'w');
        if (! $output) {
            return;
        }

        fputcsv($output, ['Reference', 'Title', 'Patient', 'Reporter', 'Date', 'Time', 'Investigation', 'RIDDOR', 'Safeguarding', 'Submitted']);
        foreach ($incidents as $incident) {
            $inv = $incident['investigation'] ?? [];
            fputcsv($output, [
                $incident['reference'],
                $incident['title'],
                $incident['patient_name'],
                $incident['reporter'],
                $incident['incident_date'],
                $incident['incident_time'],
                $inv['statusLabel'] ?? '-',
                ($inv['riddorReportable'] ?? false) ? 'Yes' : 'No',
                ($inv['safeguardingConcern'] ?? false) ? 'Yes' : 'No',
                $incident['submitted_at'],
            ]);
        }
        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.incidents.export.csv');

Route::get('/reports/incidents/export/riddor.csv', function () {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $rows = PatientIncident::query()
        ->whereHas('investigation', fn ($q) => $q->where('riddor_reportable', true))
        ->with(['patient:id,name', 'investigation'])
        ->orderByDesc('submitted_at')
        ->get();

    $filename = 'Allocare-riddor-register-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($rows): void {
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Reference', 'Patient', 'Incident date', 'Category', 'Reported at', 'HSE reference', 'Status']);
        foreach ($rows as $incident) {
            $inv = $incident->investigation;
            fputcsv($output, [
                $incident->reference,
                $incident->patient?->name ?? '-',
                $incident->incident_date?->format('Y-m-d') ?? '-',
                $inv?->riddor_category ?? '-',
                $inv?->riddor_reported_at?->format('d M Y H:i') ?? '-',
                $inv?->riddor_reference ?? '-',
                $inv?->investigation_status ?? '-',
            ]);
        }
        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.incidents.export.riddor');

Route::get('/reports/incidents/export/safeguarding.csv', function () {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $rows = PatientIncident::query()
        ->whereHas('investigation', fn ($q) => $q->where('safeguarding_concern', true))
        ->with(['patient:id,name', 'investigation'])
        ->orderByDesc('submitted_at')
        ->get();

    $filename = 'Allocare-safeguarding-referrals-'.now()->format('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($rows): void {
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Reference', 'Patient', 'Incident date', 'Referral made', 'Referral date', 'Authority', 'Reference', 'Status']);
        foreach ($rows as $incident) {
            $inv = $incident->investigation;
            fputcsv($output, [
                $incident->reference,
                $incident->patient?->name ?? '-',
                $incident->incident_date?->format('Y-m-d') ?? '-',
                $inv?->safeguarding_referral_made ? 'Yes' : 'No',
                $inv?->safeguarding_referral_at?->format('d M Y H:i') ?? '-',
                $inv?->safeguarding_authority ?? '-',
                $inv?->safeguarding_reference ?? '-',
                $inv?->investigation_status ?? '-',
            ]);
        }
        fclose($output);
    }, $filename, ['Content-Type' => 'text/csv']);
})->middleware(['auth', 'verified'])->name('reports.incidents.export.safeguarding');

Route::get('/reports/incidents/export/pdf', function () {
    if (! AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $incidents = PatientIncident::query()
        ->with(['patient:id,name', 'reportedBy:id,name'])
        ->orderByDesc('submitted_at')
        ->limit(250)
        ->get()
        ->map(fn (PatientIncident $incident) => [
            'title' => $incident->incident_title ?? '-',
            'patient' => $incident->patient?->name ?? '-',
            'reporter' => $incident->reportedBy?->name ?? '-',
            'incident_date' => $incident->incident_date?->format('Y-m-d') ?? '-',
            'incident_time' => $incident->incident_time ?? '-',
            'status' => 'Submitted',
            'submitted_at' => $incident->submitted_at->format('d M Y H:i'),
        ]);

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.incidents-pdf', [
        'incidents' => $incidents,
    ])->setPaper('a4', 'landscape');

    return $pdf->download('Allocare-incident-report-'.now()->format('Ymd-His').'.pdf');
})->middleware(['auth', 'verified'])->name('reports.incidents.export.pdf');

Route::get('/reports/incidents/{incident}', function (PatientIncident $incident) {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $incident->load(['patient', 'reportedBy', 'investigation.investigator']);

    return Inertia::render('ReportsIncidentView', [
        'incident' => map_patient_incident_detail($incident),
        'investigationStatusOptions' => IncidentInvestigation::STATUSES,
        'riddorCategoryOptions' => collect(IncidentInvestigation::RIDDOR_CATEGORIES)
            ->map(fn (string $cat) => [
                'value' => $cat,
                'label' => Str::of($cat)->replace('_', ' ')->title()->toString(),
            ])
            ->values(),
        'canManageInvestigation' => AuditTrail::canViewReports(request()->user()),
    ]);
})->middleware(['auth', 'verified'])->name('reports.incidents.show');

Route::patch('/reports/incidents/{incident}/investigation', function (Request $request, PatientIncident $incident) {
    abort_unless(AuditTrail::canViewReports($request->user()), 403);

    $investigation = $incident->investigation ?? IncidentInvestigation::query()->create([
        'patient_incident_id' => $incident->id,
        'investigation_status' => IncidentInvestigation::STATUS_PENDING,
        'due_at' => now()->addDays(7)->toDateString(),
    ]);

    $validated = $request->validate([
        'investigation_status' => ['required', 'string', 'in:'.implode(',', IncidentInvestigation::STATUSES)],
        'investigation_summary' => ['nullable', 'string', 'max:10000'],
        'root_cause' => ['nullable', 'string', 'max:10000'],
        'corrective_actions' => ['nullable', 'string', 'max:10000'],
        'due_at' => ['nullable', 'date'],
        'riddor_reportable' => ['nullable', 'boolean'],
        'riddor_category' => ['nullable', 'string', 'in:'.implode(',', IncidentInvestigation::RIDDOR_CATEGORIES)],
        'riddor_reported_at' => ['nullable', 'date'],
        'riddor_reference' => ['nullable', 'string', 'max:128'],
        'safeguarding_concern' => ['nullable', 'boolean'],
        'safeguarding_referral_made' => ['nullable', 'boolean'],
        'safeguarding_referral_at' => ['nullable', 'date'],
        'safeguarding_authority' => ['nullable', 'string', 'max:255'],
        'safeguarding_reference' => ['nullable', 'string', 'max:128'],
    ]);

    $status = $validated['investigation_status'];
    $updates = [
        'investigation_status' => $status,
        'investigator_user_id' => $request->user()->id,
        'investigation_summary' => trim((string) ($validated['investigation_summary'] ?? '')) ?: $investigation->investigation_summary,
        'root_cause' => trim((string) ($validated['root_cause'] ?? '')) ?: $investigation->root_cause,
        'corrective_actions' => trim((string) ($validated['corrective_actions'] ?? '')) ?: $investigation->corrective_actions,
        'due_at' => $validated['due_at'] ?? $investigation->due_at,
        'riddor_reportable' => $request->boolean('riddor_reportable'),
        'riddor_category' => $validated['riddor_category'] ?? null,
        'riddor_reference' => trim((string) ($validated['riddor_reference'] ?? '')) ?: null,
        'safeguarding_concern' => $request->boolean('safeguarding_concern'),
        'safeguarding_referral_made' => $request->boolean('safeguarding_referral_made'),
        'safeguarding_authority' => trim((string) ($validated['safeguarding_authority'] ?? '')) ?: null,
        'safeguarding_reference' => trim((string) ($validated['safeguarding_reference'] ?? '')) ?: null,
    ];

    if (!empty($validated['riddor_reported_at'])) {
        $updates['riddor_reported_at'] = Carbon::parse((string) $validated['riddor_reported_at']);
    }

    if (!empty($validated['safeguarding_referral_at'])) {
        $updates['safeguarding_referral_at'] = Carbon::parse((string) $validated['safeguarding_referral_at']);
    }

    if ($status === IncidentInvestigation::STATUS_IN_PROGRESS && !$investigation->investigation_started_at) {
        $updates['investigation_started_at'] = now();
    }

    if (in_array($status, [IncidentInvestigation::STATUS_COMPLETED, IncidentInvestigation::STATUS_CLOSED], true)) {
        $updates['investigation_completed_at'] = now();
    }

    $investigation->update($updates);

    AuditTrail::record(
        'updated',
        'Updated investigation for incident '.$incident->reference,
        'incident_investigation',
        (string) $investigation->id,
        $incident->patient?->name,
        ['status' => $status],
        ['patient_url_key' => $incident->patient?->url_key],
        $request,
    );

    return redirect()->route('reports.incidents.show', $incident)->with('success', 'Investigation updated.');
})->middleware(['auth', 'verified', 'role:super_admin,admin,care_manager,supervisor'])->name('reports.incidents.investigation.update');

Route::get('/api/postcode-lookup/{postcode}', function (string $postcode) {
    $postcode = strtoupper(trim(str_replace(' ', '', $postcode)));
    if (!preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/', $postcode)) {
        return response()->json(['error' => 'Invalid UK postcode format.'], 422);
    }

    $formatted = strlen($postcode) > 4
        ? substr($postcode, 0, -3).' '.substr($postcode, -3)
        : $postcode;

    $apiKey = config('services.ideal_postcodes.api_key', 'ak_test');

    Log::info('[Postcode Lookup] Request', ['postcode' => $postcode, 'formatted' => $formatted, 'provider' => 'ideal-postcodes']);

    try {
        $response = Http::timeout(5)->get("https://api.ideal-postcodes.co.uk/v1/postcodes/{$postcode}", [
            'api_key' => $apiKey,
        ]);

        Log::info('[Postcode Lookup] ideal-postcodes response', ['status' => $response->status(), 'postcode' => $postcode]);

        if ($response->ok()) {
            $results = $response->json()['result'] ?? [];
            $addresses = collect($results)->map(function ($addr) use ($formatted) {
                $line1 = trim(implode(', ', array_filter([
                    $addr['line_1'] ?? '',
                    $addr['line_2'] ?? '',
                    $addr['line_3'] ?? '',
                ])), ', ');
                $city = $addr['post_town'] ?? '';
                return [
                    'label' => implode(', ', array_filter([$line1, $city, $formatted])),
                    'address_line_1' => $line1,
                    'city' => $city,
                    'postcode' => $formatted,
                    'latitude' => $addr['latitude'] ?? null,
                    'longitude' => $addr['longitude'] ?? null,
                ];
            })->values();

            Log::info('[Postcode Lookup] Success', ['postcode' => $postcode, 'provider' => 'ideal-postcodes', 'addresses_count' => $addresses->count()]);

            return response()->json(['addresses' => $addresses]);
        }
    } catch (\Throwable $e) {
        Log::warning('[Postcode Lookup] ideal-postcodes failed, falling back to postcodes.io', ['postcode' => $postcode, 'error' => $e->getMessage()]);
    }

    Log::info('[Postcode Lookup] Trying fallback provider', ['postcode' => $formatted, 'provider' => 'postcodes.io']);

    try {
        $response = Http::timeout(5)
            ->withHeaders(['User-Agent' => 'AlloCare/1.0'])
            ->get("https://api.postcodes.io/postcodes/{$formatted}");

        Log::info('[Postcode Lookup] postcodes.io response', ['status' => $response->status(), 'postcode' => $formatted]);

        if ($response->ok()) {
            $result = $response->json()['result'] ?? [];
            $label = implode(', ', array_filter([
                $result['admin_ward'] ?? '',
                $result['admin_district'] ?? '',
                $formatted,
            ]));

            Log::info('[Postcode Lookup] Success (fallback)', ['postcode' => $formatted, 'provider' => 'postcodes.io']);

            return response()->json([
                'addresses' => [[
                    'label' => $label,
                    'address_line_1' => '',
                    'city' => $result['admin_district'] ?? '',
                    'postcode' => $formatted,
                    'latitude' => $result['latitude'] ?? null,
                    'longitude' => $result['longitude'] ?? null,
                ]],
                'manual_entry_needed' => true,
            ]);
        }

        Log::warning('[Postcode Lookup] Postcode not found', ['postcode' => $formatted, 'status' => $response->status()]);

        return response()->json(['error' => 'Postcode not found.'], 404);
    } catch (\Throwable $e) {
        Log::error('[Postcode Lookup] All providers failed', ['postcode' => $formatted, 'error' => $e->getMessage()]);

        return response()->json(['error' => 'Unable to verify postcode. Please try again.'], 503);
    }
})->middleware(['auth', 'verified'])->name('api.postcode-lookup');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

require __DIR__.'/auth.php';
