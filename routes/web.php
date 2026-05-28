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
use App\Models\MedicationReminder;
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

    $careAlerts = collect();

    $overdueReminders = MedicationReminder::query()
        ->where('dismissed', false)
        ->where('due_at', '<', now())
        ->whereDate('due_at', now()->toDateString())
        ->with(['patient:id,name,url_key', 'medication:id,name,dose'])
        ->orderByDesc('due_at')
        ->limit(4)
        ->get();

    foreach ($overdueReminders as $reminder) {
        $patientSlug = $reminder->patient?->url_key;
        $careAlerts->push([
            'label' => 'MISSED MEDICATION',
            'patient' => $reminder->patient?->name ?? 'Unknown',
            'details' => ($reminder->medication?->name ?? 'Unknown').' '.($reminder->medication?->dose ?? '').', due '.$reminder->due_at->format('H:i'),
            'action' => 'Resolve',
            'accent' => 'border-red-400',
            'panel' => 'bg-red-50',
            'time' => $reminder->due_at,
            'href' => $patientSlug ? "/patients/{$patientSlug}/mar/today" : null,
        ]);
    }

    $refusedToday = MedicationAdministration::query()
        ->whereIn('status', ['refused', 'omitted'])
        ->whereDate('created_at', now()->toDateString())
        ->with(['patient:id,name,url_key', 'medication:id,name'])
        ->orderByDesc('created_at')
        ->limit(4)
        ->get();

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
            'href' => $patientSlug ? "/patients/{$patientSlug}/mar/today" : null,
        ]);
    }

    $overdueSchedules = PatientSchedule::query()
        ->where('end_at', '<', now())
        ->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '');
        })
        ->with('patient:id,name,url_key')
        ->orderByDesc('end_at')
        ->get();

    foreach ($overdueSchedules as $schedule) {
        $careAlerts->push([
            'label' => 'MISSED VISIT',
            'patient' => $schedule->patient?->name ?? 'Unknown',
            'details' => ($schedule->purpose ?? 'Scheduled visit').' — ended '.$schedule->end_at->format('H:i'),
            'action' => 'Follow Up',
            'accent' => 'border-rose-400',
            'panel' => 'bg-rose-50',
            'time' => $schedule->end_at,
            'href' => '/schedules',
        ]);
    }

    $redAmberPatients = Patient::query()
        ->whereIn(DB::raw('LOWER(COALESCE(rag_status, ""))'), ['red', 'amber'])
        ->orderByRaw("CASE WHEN LOWER(rag_status) = 'red' THEN 0 ELSE 1 END")
        ->limit(2)
        ->get(['name', 'rag_status', 'status', 'url_key']);

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
            'href' => $patient->url_key ? "/patients/{$patient->url_key}" : null,
        ]);
    }

    $totalCareAlerts = $careAlerts->count();
    $alertPatients = $careAlerts
        ->sortByDesc('time')
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
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

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

            return [
                'label' => $dayStart->format('D d M'),
                'total' => $daySchedules->count(),
                'completed' => $daySchedules->where('status', 'completed')->count(),
                'missed' => $daySchedules->where('status', 'missed')->count(),
            ];
        })
        ->values()
        ->all();

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

    $recentActivity = collect(AuditTrail::fetchActivityLogsForUi(20))
        ->map(function (array $log) {
            return [
                'id' => $log['id'] ?? null,
                'createdAt' => $log['created_at'] ?? null,
                'user' => $log['user_name'] ?? null,
                'description' => $log['description'] ?? '-',
                'path' => $log['path'] ?? '-',
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
        'recentMissedShifts' => $recentMissedShifts,
        'recentActivity' => $recentActivity,
    ]);
})->middleware(['auth', 'verified'])->name('analytics');

Route::get('/care-alerts', function () {
    $careAlerts = collect();

    $overdueReminders = MedicationReminder::query()
        ->where('dismissed', false)
        ->where('due_at', '<', now())
        ->with(['patient:id,name,url_key', 'medication:id,name,dose'])
        ->orderByDesc('due_at')
        ->get();

    foreach ($overdueReminders as $reminder) {
        $patientSlug = $reminder->patient?->url_key;
        $careAlerts->push([
            'label' => 'MISSED MEDICATION',
            'patient' => $reminder->patient?->name ?? 'Unknown',
            'details' => ($reminder->medication?->name ?? 'Unknown').' '.($reminder->medication?->dose ?? '').', due '.$reminder->due_at->format('H:i d M'),
            'action' => 'Resolve',
            'accent' => 'border-red-400',
            'panel' => 'bg-red-50',
            'time' => $reminder->due_at,
            'href' => $patientSlug ? "/patients/{$patientSlug}/mar/today" : null,
        ]);
    }

    $refusedToday = MedicationAdministration::query()
        ->whereIn('status', ['refused', 'omitted'])
        ->whereDate('created_at', now()->toDateString())
        ->with(['patient:id,name,url_key', 'medication:id,name'])
        ->orderByDesc('created_at')
        ->get();

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
            'href' => $patientSlug ? "/patients/{$patientSlug}/mar/today" : null,
        ]);
    }

    $overdueSchedules = PatientSchedule::query()
        ->where('end_at', '<', now())
        ->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '');
        })
        ->with('patient:id,name,url_key')
        ->orderByDesc('end_at')
        ->get();

    foreach ($overdueSchedules as $schedule) {
        $careAlerts->push([
            'label' => 'MISSED VISIT',
            'patient' => $schedule->patient?->name ?? 'Unknown',
            'details' => ($schedule->purpose ?? 'Scheduled visit').' — ended '.$schedule->end_at->format('H:i d M'),
            'action' => 'Follow Up',
            'accent' => 'border-rose-400',
            'panel' => 'bg-rose-50',
            'time' => $schedule->end_at,
            'href' => '/schedules',
        ]);
    }

    $redAmberPatients = Patient::query()
        ->whereIn(DB::raw('LOWER(COALESCE(rag_status, ""))'), ['red', 'amber'])
        ->orderByRaw("CASE WHEN LOWER(rag_status) = 'red' THEN 0 ELSE 1 END")
        ->get(['name', 'rag_status', 'status', 'url_key']);

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
            'href' => $patient->url_key ? "/patients/{$patient->url_key}" : null,
        ]);
    }

    $allAlerts = $careAlerts
        ->sortByDesc('time')
        ->map(fn ($a) => collect($a)->except('time')->all())
        ->values();

    return Inertia::render('CareAlerts', ['alerts' => $allAlerts]);
})->middleware(['auth', 'verified'])->name('care-alerts');

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
        'spo2' => $vital->spo2,
        'otherObservation' => $vital->other_observation,
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
})->middleware(['auth', 'verified'])->name('reports');

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

    $patient = Patient::query()->create([
        'url_key' => $urlKey,
        'slug' => $slug,
        'name' => $name,
        'reference' => '#'.strtoupper($urlKey),
        'nhs_number' => $payload['nhs_number'] ?: null,
        'photo_path' => request()->hasFile('photo') ? request()->file('photo')->store('patient-photos', 'public') : null,
        'dob' => $payload['dob'] ?: null,
        'allergies' => ! empty($payload['allergies'] ?? null) ? array_map('trim', explode(',', (string) $payload['allergies'])) : ['None'],
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
})->middleware(['auth', 'verified'])->name('patients.rag-status');

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

    return Inertia::render('PatientObservations', [
        'patientSlug' => $patient,
        'patient' => [
            'name' => $record->name,
            'ragStatus' => $record->rag_status,
        ],
        'observations' => $observations,
        'latestVitals' => $latestVitals ? [
            'heartRate' => $latestVitals->heart_rate,
            'bpSystolic' => $latestVitals->bp_systolic,
            'spo2' => $latestVitals->spo2,
            'recordedAt' => optional($latestVitals->recorded_at ?? $latestVitals->created_at)->toIso8601String(),
        ] : null,
    ]);
})->middleware(['auth', 'verified'])->name('patients.observations');

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
            'dueToday' => max(0, $dueToday),
            'overdueReminders' => $overdueReminders,
        ],
    ]);
})->middleware(['auth', 'verified'])->name('patients.mar');

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

            return [
                'id' => $medication->id,
                'medicine' => $medication->name,
                'time' => $medication->scheduled_time ? Carbon::createFromFormat('H:i:s', (string) $medication->scheduled_time)->format('H:i') : '',
                'route' => $medication->route ?? '',
                'dose' => $medication->dose ?? '',
                'status' => Str::title((string) ($latestAdministration?->status ?? 'Due')),
                'by' => $administeredByName,
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

    return Inertia::render('PatientMARDetail', [
        'patientSlug' => $patient,
        'marSlug' => $mar,
        'initialRows' => $medications,
        'prnMedications' => $prnMedications,
        'reminders' => $reminders,
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
        'rows.*.status' => ['required', 'string', 'in:Given,Due,Refused,Omitted,Self-Administered'],
        'rows.*.reason' => ['nullable', 'string', 'max:500'],
        'rows.*.witness_name' => ['nullable', 'string', 'max:255'],
        'rows.*.is_prn_dose' => ['nullable', 'boolean'],
    ]);

    $errors = [];

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

        if (in_array($status, ['refused', 'omitted']) && empty($row['reason'])) {
            $errors[] = "Row {$idx}: A reason is required when status is refused or omitted.";
            continue;
        }

        if ($medication->is_controlled && $status === 'given' && empty($row['witness_name'])) {
            $errors[] = "Row {$idx}: '{$medication->name}' is a controlled drug and requires a witness signature.";
            continue;
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

        MedicationAdministration::query()->create([
            'patient_id' => $patientRecord->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => request()->user()?->id,
            'status' => $status,
            'administered_at' => $status === 'given' || $status === 'self_administered' ? now() : null,
            'scheduled_for' => $scheduledFor,
            'source_mar_slug' => $mar,
            'reason' => $row['reason'] ?? null,
            'witness_name' => $row['witness_name'] ?? null,
        ]);

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
})->middleware(['auth', 'verified'])->name('patients.mar.save');

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
})->middleware(['auth', 'verified'])->name('patients.medications.store');

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
})->middleware(['auth', 'verified'])->name('patients.medications.update');

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
})->middleware(['auth', 'verified'])->name('patients.documents.save');

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
            'latitude' => $patientRecord->latitude ? (float) $patientRecord->latitude : null,
            'longitude' => $patientRecord->longitude ? (float) $patientRecord->longitude : null,
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
        'other_observation' => ['nullable', 'string', 'max:5000'],
    ]);

    $otherObservation = trim((string) ($payload['other_observation'] ?? ''));

    $vital = PatientVital::query()->create([
        'patient_id' => $patientRecord->id,
        'heart_rate' => (int) $payload['heart_rate'],
        'bp_systolic' => (int) $payload['bp_systolic'],
        'spo2' => (int) $payload['spo2'],
        'other_observation' => $otherObservation !== '' ? $otherObservation : null,
        'recorded_at' => now(),
        'recorded_by_user_id' => request()->user()?->id,
    ]);

    AuditTrail::record(
        'created',
        'Recorded clinical observation for '.$patientRecord->name,
        'vital',
        (string) $vital->id,
        $patientRecord->name,
        [
            'heart_rate' => $vital->heart_rate,
            'bp_systolic' => $vital->bp_systolic,
            'spo2' => $vital->spo2,
        ],
        ['patient_url_key' => $patient],
    );

    return redirect()->back()->with('success', 'Clinical observation recorded successfully.');
})->middleware(['auth', 'verified'])->name('patients.vitals.store');

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
})->middleware(['auth', 'verified'])->name('employees.store');

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
})->middleware(['auth', 'verified'])->name('employees.profile');

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
})->middleware(['auth', 'verified'])->name('employees.update');

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
})->middleware(['auth', 'verified'])->name('employees.training.store');

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
})->middleware(['auth', 'verified'])->name('employees.competencies.store');

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
})->middleware(['auth', 'verified'])->name('employees.supervisions.store');

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
})->middleware(['auth', 'verified'])->name('employees.documents.store');

Route::get('/employees/{user}/documents/{document}/download', function (User $user, StaffDocument $document) {
    abort_unless($document->user_id === $user->id, 404);
    abort_unless(Storage::disk('public')->exists($document->file_path), 404);

    return Storage::disk('public')->download($document->file_path, $document->file_name);
})->middleware(['auth', 'verified'])->name('employees.documents.download');

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

Route::get('/reports/incidents', function () {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $incidents = FormSnapshot::query()
        ->where('form_key', 'like', 'incident:%')
        ->orderByDesc('updated_at')
        ->get()
        ->map(function ($snapshot) {
            $data = $snapshot->data ?? [];
            $patientUrlKey = str_replace('incident:', '', $snapshot->form_key);
            $patient = Patient::query()->where('url_key', $patientUrlKey)->first();
            $reporter = $snapshot->updated_by_user_id
                ? User::find($snapshot->updated_by_user_id)
                : null;

            return [
                'id' => $snapshot->id,
                'title' => $data['incidentTitle'] ?? '-',
                'patient_name' => $patient?->name ?? $patientUrlKey,
                'patient_url_key' => $patientUrlKey,
                'reporter' => $reporter?->name ?? ($data['reporterName'] ?? 'Unknown'),
                'incident_date' => $data['incidentDate'] ?? $snapshot->updated_at->format('Y-m-d'),
                'incident_time' => $data['incidentTime'] ?? '-',
                'location' => $data['location'] ?? '-',
                'tags' => $data['tags'] ?? ($data['selectedTags'] ?? []),
                'status' => $data['status'] ?? 'Submitted',
                'duration_minutes' => $data['durationMinutes'] ?? ($data['incidentDuration'] ?? null),
                'submitted_at' => $snapshot->updated_at->format('d M Y H:i'),
            ];
        });

    $totalIncidents = $incidents->count();
    $submittedCount = $incidents->where('status', 'Submitted')->count();
    $draftCount = $totalIncidents - $submittedCount;
    $byPatient = $incidents->groupBy('patient_name')->map->count()->sortByDesc(fn ($v) => $v);

    return Inertia::render('ReportsIncidents', [
        'incidents' => $incidents->values(),
        'stats' => [
            'total' => $totalIncidents,
            'submitted' => $submittedCount,
            'drafts' => $draftCount,
            'byPatient' => $byPatient,
        ],
    ]);
})->middleware(['auth', 'verified'])->name('reports.incidents');

Route::get('/reports/incidents/{id}', function (int $id) {
    if (!AuditTrail::canViewReports(request()->user())) {
        abort(403);
    }

    $snapshot = FormSnapshot::query()->findOrFail($id);
    $data = $snapshot->data ?? [];
    $patientUrlKey = str_replace('incident:', '', $snapshot->form_key);
    $patient = Patient::query()->where('url_key', $patientUrlKey)->first();
    $reporter = $snapshot->updated_by_user_id
        ? User::find($snapshot->updated_by_user_id)
        : null;

    return Inertia::render('ReportsIncidentView', [
        'incident' => [
            'id' => $snapshot->id,
            'form_key' => $snapshot->form_key,
            'title' => $data['incidentTitle'] ?? '-',
            'patient_name' => $patient?->name ?? $patientUrlKey,
            'patient_url_key' => $patientUrlKey,
            'patient_dob' => $patient?->date_of_birth ?? '-',
            'patient_address' => $patient?->address ?? '-',
            'reporter' => $data['reporterName'] ?? ($reporter?->name ?? 'Unknown'),
            'incident_date' => $data['incidentDate'] ?? '-',
            'incident_time' => $data['incidentTime'] ?? '-',
            'location' => $data['location'] ?? '-',
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
            'status' => $data['status'] ?? 'Draft',
            'submitted_at' => $snapshot->updated_at->format('d M Y H:i'),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('reports.incidents.show');

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
