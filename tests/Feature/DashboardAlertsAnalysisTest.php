<?php

namespace Tests\Feature;

use App\Models\MedicationAdministration;
use App\Models\MedicationReminder;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientSchedule;
use App\Models\PatientVital;
use App\Models\ScheduleVisitTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAlertsAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_live_alerts_analysis(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-dash-analysis',
            'slug' => 'pt-dash-analysis',
            'name' => 'Analysis Patient',
        ]);

        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Paracetamol',
            'active' => true,
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => $user->id,
            'status' => 'given',
            'administered_at' => now(),
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => $user->id,
            'status' => 'refused',
            'administered_at' => now(),
            'reason' => 'Patient declined',
        ]);

        MedicationReminder::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'due_at' => now()->subHour(),
            'dismissed' => false,
        ]);

        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'purpose' => 'Morning visit',
        ]);

        ScheduleVisitTask::query()->create([
            'patient_schedule_id' => $schedule->id,
            'task_key' => 'personal_care',
            'task_label' => 'Personal care',
            'sort_order' => 0,
            'outcome' => 'completed',
            'completed_at' => now()->subHour(),
            'completed_by_user_id' => $user->id,
        ]);

        ScheduleVisitTask::query()->create([
            'patient_schedule_id' => $schedule->id,
            'task_key' => 'observations',
            'task_label' => 'Observations',
            'sort_order' => 1,
            'outcome' => null,
        ]);

        PatientVital::query()->create([
            'patient_id' => $patient->id,
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now(),
            'heart_rate' => 72,
            'bp_systolic' => 120,
            'spo2' => 88,
        ]);

        PatientVital::query()->create([
            'patient_id' => $patient->id,
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now(),
            'heart_rate' => 76,
            'bp_systolic' => 118,
            'spo2' => 97,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('dashboardStats.alertsAnalysis', 3)
                ->where('dashboardStats.alertsAnalysis.0.label', 'Medications')
                ->where('dashboardStats.alertsAnalysis.0.total', 3)
                ->where('dashboardStats.alertsAnalysis.0.resolvedCount', 1)
                ->where('dashboardStats.alertsAnalysis.0.flaggedCount', 1)
                ->where('dashboardStats.alertsAnalysis.0.missedCount', 1)
                ->where('dashboardStats.alertsAnalysis.1.label', 'Personal Care')
                ->where('dashboardStats.alertsAnalysis.1.resolvedCount', 1)
                ->where('dashboardStats.alertsAnalysis.1.missedCount', 1)
                ->where('dashboardStats.alertsAnalysis.2.label', 'Observations')
                ->where('dashboardStats.alertsAnalysis.2.resolvedCount', 1)
                ->where('dashboardStats.alertsAnalysis.2.flaggedCount', 1)
                ->where('dashboardStats.alertsAnalysis.2.missedCount', 1)
                ->where('dashboardStats.alertsAnalysis.0.key', 'medications')
                ->where('dashboardStats.alertsAnalysis.0.drillDownHref', fn ($href) => is_string($href) && str_contains($href, 'reports/medications'))
                ->where('dashboardStats.alertsAnalysis.1.drillDownHref', fn ($href) => is_string($href) && str_contains($href, 'reports/schedules'))
                ->where('dashboardStats.alertsAnalysis.2.drillDownHref', fn ($href) => is_string($href) && str_contains($href, 'reports/clinical-outcomes')));
    }

    public function test_alerts_analysis_drill_down_for_carer_links_to_operational_pages(): void
    {
        $carer = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);

        $this->actingAs($carer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardStats.alertsAnalysis.0.drillDownHref', route('care-alerts'))
                ->where('dashboardStats.alertsAnalysis.1.drillDownHref', route('schedules'))
                ->where('dashboardStats.alertsAnalysis.2.drillDownHref', route('care-alerts')));
    }
}
