<?php

use App\Http\Controllers\ProfileController;
use App\Models\PatientDocumentForm;
use App\Models\PatientCarePlanForm;
use App\Models\PatientCarePlanSummary;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientSchedule;
use App\Models\PatientVital;
use App\Models\MedicationAdministration;
use App\Models\FormSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $now = now();
    $startOfWeek = $now->copy()->startOfWeek();
    $endOfWeek = $now->copy()->endOfWeek();

    $weeklySchedules = PatientSchedule::query()
        ->whereBetween('start_at', [$startOfWeek, $endOfWeek]);

    $weeklyVisitsTotal = (clone $weeklySchedules)->count();
    $weeklyVisitsInProgress = (clone $weeklySchedules)
        ->where('start_at', '<=', $now)
        ->where('end_at', '>=', $now)
        ->count();
    $weeklyVisitsCompleted = (clone $weeklySchedules)
        ->where('end_at', '<', $now)
        ->whereNotNull('notes')
        ->where('notes', '!=', '')
        ->count();
    $weeklyVisitsMissed = (clone $weeklySchedules)
        ->where('end_at', '<', $now)
        ->where(function ($query) {
            $query->whereNull('notes')->orWhere('notes', '');
        })
        ->count();
    // Keep "Partial" as the remaining scheduled visits in the week (typically upcoming).
    $weeklyVisitsPartial = max(0, $weeklyVisitsTotal - $weeklyVisitsCompleted - $weeklyVisitsInProgress - $weeklyVisitsMissed);

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

    $alertPatients = Patient::query()
        ->whereIn(DB::raw('LOWER(COALESCE(rag_status, ""))'), ['red', 'amber'])
        ->orderByRaw("CASE WHEN LOWER(rag_status) = 'red' THEN 0 ELSE 1 END")
        ->limit(3)
        ->get(['name', 'rag_status', 'status'])
        ->map(function ($patient) {
            $severity = strtolower((string) ($patient->rag_status ?? 'amber'));
            $isRed = $severity === 'red';

            return [
                'label' => $isRed ? 'HIGH RISK PATIENT' : 'ELEVATED RISK PATIENT',
                'patient' => $patient->name ?: 'Unknown patient',
                'details' => $patient->status ? 'Status: '.$patient->status : 'Requires clinical review',
                'action' => $isRed ? 'Review Now' : 'Review',
                'accent' => $isRed ? 'border-red-400' : 'border-amber-400',
                'panel' => $isRed ? 'bg-red-50' : 'bg-amber-50',
            ];
        })
        ->values();

    return Inertia::render('Dashboard', [
        'dashboardStats' => [
            'visits' => [
                'total' => $weeklyVisitsTotal,
                'metrics' => [
                    'complete' => $weeklyVisitsCompleted,
                    'inProgress' => $weeklyVisitsInProgress,
                    'partial' => $weeklyVisitsPartial,
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
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

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

            return [
                'id' => $entry->id,
                'patientName' => $entry->patient?->name ?? 'Unknown patient',
                'patientUrlKey' => $entry->patient?->url_key,
                'patientReference' => $entry->patient?->reference,
                'staffName' => $staffName,
                'assignedUserId' => $entry->assigned_user_id,
                'startAt' => optional($entry->start_at)->toIso8601String(),
                'endAt' => optional($entry->end_at)->toIso8601String(),
                'purpose' => $entry->purpose,
                'notes' => $entry->notes,
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
    $startAt = Carbon::parse($payload['visit_date'].' '.$payload['start_time']);
    $endAt = Carbon::parse($payload['visit_date'].' '.$payload['end_time']);

    if ($endAt->lessThanOrEqualTo($startAt)) {
        throw ValidationException::withMessages([
            'end_time' => 'End time must be after the start time.',
        ]);
    }

    $assignedUser = resolve_care_worker_user_or_fail((int) $payload['assigned_user_id']);

    PatientSchedule::query()->create([
        'patient_id' => $patient->id,
        'assigned_user_id' => $assignedUser->id,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'purpose' => $payload['purpose'] ?? null,
        'notes' => $payload['notes'] ?? null,
        'created_by_user_id' => request()->user()?->id,
    ]);

    return redirect()->route('schedules')->with('success', 'Visit scheduled successfully.');
})->middleware(['auth', 'verified'])->name('schedules.store');

Route::patch('/schedules/{schedule}', function (PatientSchedule $schedule) {
    $payload = request()->validate([
        'patient_url_key' => ['required', 'string', 'exists:patients,url_key'],
        'visit_date' => ['required', 'date'],
        'start_time' => ['required', 'date_format:H:i'],
        'end_time' => ['required', 'date_format:H:i'],
        'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
    ]);

    $patient = Patient::query()->where('url_key', $payload['patient_url_key'])->firstOrFail();
    $startAt = Carbon::parse($payload['visit_date'].' '.$payload['start_time']);
    $endAt = Carbon::parse($payload['visit_date'].' '.$payload['end_time']);

    if ($endAt->lessThanOrEqualTo($startAt)) {
        throw ValidationException::withMessages([
            'end_time' => 'End time must be after the start time.',
        ]);
    }

    $updates = [
        'patient_id' => $patient->id,
        'start_at' => $startAt,
        'end_at' => $endAt,
    ];

    // Future-safe: if a reassign action reuses this endpoint, enforce care-worker only.
    if (array_key_exists('assigned_user_id', $payload) && $payload['assigned_user_id'] !== null) {
        $updates['assigned_user_id'] = resolve_care_worker_user_or_fail((int) $payload['assigned_user_id'])->id;
    }

    $schedule->update($updates);

    return redirect()->route('schedules')->with('success', 'Schedule updated successfully.');
})->middleware(['auth', 'verified'])->name('schedules.reschedule');

Route::get('/admin/activity-logs', function () {
    $user = request()->user();
    $isAdmin = user_has_primary_role($user, ['admin', 'super_admin']);

    abort_unless($isAdmin, 403, 'Only admin users can view activity logs.');

    if (Schema::hasTable('user_activity_logs')) {
        $logs = DB::table('user_activity_logs')
            ->leftJoin('users', 'users.id', '=', 'user_activity_logs.user_id')
            ->select([
                'user_activity_logs.*',
                'users.name as user_name_field',
                'users.first_name as user_first_name',
                'users.surname as user_surname',
            ])
            ->orderByDesc('user_activity_logs.id')
            ->limit(250)
            ->get()
            ->map(function ($row) {
                $fullName = trim((string) (($row->user_first_name ?? '').' '.($row->user_surname ?? '')));
                if ($fullName === '') {
                    $fullName = $row->user_name_field ?? null;
                }

                return [
                    'id' => $row->id ?? null,
                    'created_at' => $row->created_at ?? null,
                    'user_id' => $row->user_id ?? null,
                    'user_name' => $fullName,
                    'action' => $row->action ?? null,
                    'description' => $row->description ?? null,
                    'path' => $row->path ?? null,
                    'method' => $row->method ?? null,
                    'status' => $row->status ?? null,
                    'ip_address' => $row->ip_address ?? null,
                ];
            })
            ->values();

        return Inertia::render('AdminActivityLogs', [
            'logs' => $logs,
            'tableAvailable' => true,
            'logSource' => 'database',
        ]);
    }

    $logs = [];
    $auditLogPath = storage_path('logs/audit-actions.log');
    if (File::exists($auditLogPath)) {
        $lines = preg_split('/\r\n|\r|\n/', File::get($auditLogPath)) ?: [];
        $recentLines = array_slice(array_values(array_filter($lines)), -250);

        foreach (array_reverse($recentLines) as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $logs[] = [
                'id' => null,
                'created_at' => $decoded['timestamp'] ?? null,
                'user_id' => $decoded['user_id'] ?? null,
                'user_name' => $decoded['user_name'] ?? null,
                'action' => $decoded['method'] ?? null,
                'description' => $decoded['error'] ?? 'Request event',
                'path' => $decoded['path'] ?? null,
                'method' => $decoded['method'] ?? null,
                'status' => $decoded['status'] ?? null,
                'ip_address' => $decoded['ip'] ?? null,
            ];
        }
    }

    return Inertia::render('AdminActivityLogs', [
        'logs' => $logs,
        'tableAvailable' => true,
        'logSource' => 'audit_file',
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
        'date_of_birth' => ['required', 'date'],
        'gender' => ['required', 'string', 'max:50'],
        'primary_diagnosis' => ['nullable', 'string', 'max:500'],
        'severe_allergies' => ['nullable', 'string', 'max:500'],
        'rag_status' => ['required', 'string', 'in:green,amber,red'],
        'staffing_ratio' => ['required', 'string', 'max:50'],
        'address_line_1' => ['required', 'string', 'max:255'],
        'city' => ['required', 'string', 'max:255'],
        'postcode' => ['required', 'string', 'max:50'],
        'phone_number' => ['required', 'string', 'regex:/^07\d{9}$/'],
        'email_address' => ['required', 'email', 'max:255'],
        'next_of_kin' => ['required', 'string', 'max:255'],
        'next_of_kin_tel' => ['required', 'string', 'regex:/^07\d{9}$/'],
        'next_of_kin_email' => ['required', 'email', 'max:255'],
        'other_relevant_people' => ['nullable', 'string', 'max:1000'],
        'social_services_number' => ['required', 'string', 'max:100'],
        'weight_kg' => ['required', 'numeric', 'between:1,500'],
        'height_m' => ['required', 'numeric', 'between:0.3,3'],
        'start_date' => ['required', 'date'],
        'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
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
        'phone' => ['required', 'string', 'max:100'],
        'status' => ['required', 'string', 'in:ACTIVE,OVERDUE,ON LEAVE'],
    ]);

    $name = trim($payload['name']);
    $slug = Str::slug($name);
    $urlKey = 'ac-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

    while (Patient::query()->where('url_key', $urlKey)->exists()) {
        $urlKey = 'ac-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    $patient = Patient::query()->create([
        'url_key' => $urlKey,
        'slug' => $slug,
        'name' => $name,
        'reference' => '#'.strtoupper($urlKey),
        'nhs_number' => $payload['nhs_number'] ?: null,
        'photo_path' => request()->file('photo')->store('patient-photos', 'public'),
        'dob' => $payload['dob'] ?: null,
        'allergies' => $payload['allergies'] ? array_map('trim', explode(',', $payload['allergies'])) : ['None'],
        'address' => $payload['address'] ?: null,
        'phone' => $payload['phone'] ?: null,
        'status' => $payload['status'],
        'rag_status' => $payload['rag_status'],
        'staffing_ratio' => $payload['staffing_ratio'],
        'next_of_kin' => $payload['next_of_kin'],
        'next_of_kin_tel' => $payload['next_of_kin_tel'],
        'next_of_kin_email' => $payload['next_of_kin_email'],
        'other_relevant_people' => $payload['other_relevant_people'] ?: null,
        'social_services_number' => $payload['social_services_number'],
        'avatar' => 'bg-slate-300',
    ]);

    return redirect()->route('patients')->with('success', 'Patient created successfully.');
})->middleware(['auth', 'verified'])->name('patients.store');

Route::get('/patients/{patient}', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $shiftSnapshot = FormSnapshot::query()->where('form_key', "shift-checkin:{$patient}")->first();
    $latestVitals = PatientVital::query()
        ->where('patient_id', $record->id)
        ->latest('recorded_at')
        ->latest('id')
        ->first();
    $activeAlerts = [];
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
                $activeAlerts[] = "Last Visit started {$lateMinutes} minute".($lateMinutes === 1 ? '' : 's')." late.";
            }
        } catch (\Throwable) {
            // Ignore malformed snapshot dates so profile rendering remains stable.
        }
    }

    return Inertia::render('PatientRecord', [
        'patientSlug' => $patient,
        'patient' => [
            'name' => $record->name,
            'nhsNumber' => $record->nhs_number,
            'dob' => $record->dob,
            'address' => $record->address,
            'phone' => $record->phone,
            'status' => $record->status,
            'ragStatus' => $record->rag_status,
            'staffingRatio' => $record->staffing_ratio,
            'nextOfKin' => $record->next_of_kin,
            'nextOfKinTel' => $record->next_of_kin_tel,
            'nextOfKinEmail' => $record->next_of_kin_email,
            'otherRelevantPeople' => $record->other_relevant_people,
            'socialServicesNumber' => $record->social_services_number,
            'allergies' => is_array($record->allergies) ? $record->allergies : [],
            'photoUrl' => $record->photo_path ? route('patients.photo', ['patientRecord' => $record->id]) : null,
            'updatedAt' => optional($record->updated_at)->format('H:i'),
        ],
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

Route::get('/patients/{patient}/care-plans', function (string $patient) {
    $record = Patient::query()->where('url_key', $patient)->firstOrFail();
    $carePlanForms = PatientCarePlanForm::query()
        ->where('patient_slug', $patient)
        ->get(['plan_slug', 'status', 'updated_at', 'submitted_at', 'updated_by_user_id']);

    $updaterIds = $carePlanForms
        ->pluck('updated_by_user_id')
        ->filter()
        ->unique()
        ->values();

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

    $carePlanSnapshots = $carePlanForms
        ->mapWithKeys(function (PatientCarePlanForm $form) use ($updaterNamesById) {
            return [
                $form->plan_slug => [
                    'status' => $form->status,
                    'lastUpdatedAt' => optional($form->updated_at)->toIso8601String(),
                    'submittedAt' => optional($form->submitted_at)->toIso8601String(),
                    'author' => $updaterNamesById[$form->updated_by_user_id] ?? null,
                ],
            ];
        });

    return Inertia::render('PatientCarePlans', [
        'patientSlug' => $patient,
        'patient' => [
            'name' => $record->name,
            'reference' => $record->reference ?? 'Not assigned',
            'dob' => $record->dob ?? 'Not available',
            'allergies' => is_array($record->allergies) ? $record->allergies : [],
        ],
        'carePlanSnapshots' => $carePlanSnapshots,
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

    return redirect()->back()->with('success', 'Care plan saved successfully.');
})->middleware(['auth', 'verified'])->name('patients.careplans.save');

Route::get('/patients/{patient}/risk-assessments', function (string $patient) {
    return Inertia::render('PatientRiskAssessments', [
        'patientSlug' => $patient,
    ]);
})->middleware(['auth', 'verified'])->name('patients.risks');

Route::get('/patients/{patient}/risk-assessments/{risk}', function (string $patient, string $risk) {
    return Inertia::render('PatientRiskAssessmentDetail', [
        'patientSlug' => $patient,
        'riskSlug' => $risk,
    ]);
})->middleware(['auth', 'verified'])->name('patients.risks.show');

Route::get('/patients/{patient}/mar', function (string $patient) {
    return Inertia::render('PatientMAR', [
        'patientSlug' => $patient,
    ]);
})->middleware(['auth', 'verified'])->name('patients.mar');

Route::get('/patients/{patient}/mar/{mar}', function (string $patient, string $mar) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $medications = PatientMedication::query()
        ->where('patient_id', $patientRecord->id)
        ->where('active', true)
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

            return [
                'id' => $medication->id,
                'medicine' => $medication->name,
                'time' => $medication->scheduled_time ? Carbon::createFromFormat('H:i:s', (string) $medication->scheduled_time)->format('H:i') : '',
                'route' => $medication->route ?? '',
                'dose' => $medication->dose ?? '',
                'status' => Str::title((string) ($latestAdministration?->status ?? 'Due')),
                'by' => $administeredByName,
            ];
        })
        ->values();

    return Inertia::render('PatientMARDetail', [
        'patientSlug' => $patient,
        'marSlug' => $mar,
        'initialRows' => $medications,
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
        'rows.*.status' => ['required', 'string', 'in:Given,Due,Refused'],
    ]);

    foreach ($payload['rows'] as $row) {
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
        } else {
            $medication->update([
                'name' => $row['medicine'],
                'route' => $row['route'] ?? null,
                'dose' => $row['dose'] ?? null,
                'scheduled_time' => !empty($row['time']) ? $row['time'].':00' : null,
            ]);
        }

        $scheduledFor = null;
        if (!empty($row['time'])) {
            $scheduledFor = Carbon::parse(now()->toDateString().' '.$row['time']);
        }

        MedicationAdministration::query()->create([
            'patient_id' => $patientRecord->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => request()->user()?->id,
            'status' => strtolower((string) $row['status']),
            'administered_at' => now(),
            'scheduled_for' => $scheduledFor,
            'source_mar_slug' => $mar,
        ]);
    }

    return redirect()->back()->with('success', 'eMAR saved successfully.');
})->middleware(['auth', 'verified'])->name('patients.mar.save');

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

    return redirect()->back()->with('success', 'Document saved successfully.');
})->middleware(['auth', 'verified'])->name('patients.documents.save');

Route::get('/patients/{patient}/incidents/create', function (string $patient) {
    $snapshot = FormSnapshot::query()->where('form_key', "incident:{$patient}")->first();
    return Inertia::render('IncidentReport', [
        'patientSlug' => $patient,
        'incidentStatus' => 'new',
        'initialSnapshot' => $snapshot?->data ?? [],
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
        ->where('start_at', '>=', now())
        ->orderBy('start_at')
        ->first();
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

    return Inertia::render('ShiftCheckIn', [
        'patientSlug' => $patient,
        'initialSnapshot' => $snapshot?->data ?? [],
        'patientContext' => [
            'name' => $patientRecord->name,
            'location' => $patientRecord->address ?: 'Location not provided',
            'scheduledStartAt' => $nextVisit?->start_at?->toIso8601String(),
            'scheduledEndAt' => $nextVisit?->end_at?->toIso8601String(),
            'scheduledWindow' => $nextVisit
                ? optional($nextVisit->start_at)->format('H:i').' - '.optional($nextVisit->end_at)->format('H:i')
                : 'Not scheduled',
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

Route::post('/patients/{patient}/vitals', function (string $patient) {
    $patientRecord = Patient::query()->where('url_key', $patient)->firstOrFail();
    $payload = request()->validate([
        'heart_rate' => ['required', 'integer', 'between:20,260'],
        'bp_systolic' => ['required', 'integer', 'between:40,300'],
        'spo2' => ['required', 'integer', 'between:50,100'],
    ]);

    PatientVital::query()->create([
        'patient_id' => $patientRecord->id,
        'heart_rate' => (int) $payload['heart_rate'],
        'bp_systolic' => (int) $payload['bp_systolic'],
        'spo2' => (int) $payload['spo2'],
        'recorded_at' => now(),
        'recorded_by_user_id' => request()->user()?->id,
    ]);

    return redirect()->back()->with('success', 'Vitals recorded successfully.');
})->middleware(['auth', 'verified'])->name('patients.vitals.store');

Route::get('/patients/{patient}/logs', function (string $patient) {
    $user = request()->user();
    $isAdmin = user_has_primary_role($user, ['admin', 'super_admin']);

    abort_unless($isAdmin, 403, 'Only admin users can view logs.');

    $logPath = storage_path('logs/audit-actions.log');
    $entries = [];

    if (File::exists($logPath)) {
        $lines = preg_split('/\r\n|\r|\n/', File::get($logPath)) ?: [];
        $recentLines = array_slice(array_values(array_filter($lines)), -500);

        foreach (array_reverse($recentLines) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }
    }

    return Inertia::render('PatientLogs', [
        'patientSlug' => $patient,
        'logs' => $entries,
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

    $user->update([
        'account_status' => $payload['account_status'],
    ]);

    return redirect()->route('employees')->with('success', 'Employee account status updated.');
})->middleware(['auth', 'verified'])->name('employees.account-status');

Route::get('/employees/{user}/photo', function (User $user) {
    abort_unless($user->photo_path && Storage::disk('public')->exists($user->photo_path), 404);

    return Storage::disk('public')->response($user->photo_path);
})->middleware(['auth', 'verified'])->name('employees.photo');

Route::get('/employees/create', function () {
    $snapshot = FormSnapshot::query()->where('form_key', 'employee-create')->first();
    return Inertia::render('EmployeesCreate', [
        'initialSnapshot' => $snapshot?->data ?? [],
    ]);
})->middleware(['auth', 'verified'])->name('employees.create');

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

    $normalizedDateOfBirth = null;
    $rawDateOfBirth = trim((string) ($payload['date_of_birth'] ?? ''));
    if ($rawDateOfBirth !== '') {
        $acceptedFormats = ['d/m/Y', 'Y-m-d'];
        foreach ($acceptedFormats as $format) {
            try {
                $parsedDate = Carbon::createFromFormat($format, $rawDateOfBirth);
                if ($parsedDate !== false && $parsedDate->format($format) === $rawDateOfBirth) {
                    $normalizedDateOfBirth = $parsedDate->format('Y-m-d');
                    break;
                }
            } catch (\Throwable $e) {
                // Try the next accepted format.
            }
        }

        if ($normalizedDateOfBirth === null) {
            throw ValidationException::withMessages([
                'date_of_birth' => 'Use DD/MM/YYYY or YYYY-MM-DD for date of birth.',
            ]);
        }
    }

    User::query()->create([
        'name' => $fullName,
        'email' => $payload['email'],
        'password' => Hash::make($payload['password']),
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
        'photo_path' => request()->hasFile('photo') ? request()->file('photo')->store('employee-photos', 'public') : null,
        'mfa_enabled' => array_key_exists('mfa_enabled', $payload) ? (bool) $payload['mfa_enabled'] : false,
    ]);

    FormSnapshot::query()->where('form_key', 'employee-create')->delete();

    return redirect()->route('employees')->with('success', 'Employee created successfully.');
})->middleware(['auth', 'verified'])->name('employees.store');

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

    return redirect()->back();
})->middleware(['auth', 'verified'])->name('form-snapshots.save');

Route::get('/team', function () {
    return redirect()->route('employees');
})->middleware(['auth', 'verified'])->name('team');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
