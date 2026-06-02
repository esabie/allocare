<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientRiskAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareAlertsLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_alerts_page_includes_href_for_risk_review(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-alert-link',
            'slug' => 'pt-alert-link',
            'name' => 'Alert Link Patient',
        ]);

        PatientRiskAssessment::query()->create([
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
            'risk_level' => 'high',
            'status' => 'active',
            'last_reviewed_at' => now()->subMonths(4)->toDateString(),
            'next_review_due_at' => now()->subDay()->toDateString(),
            'review_cycle_months' => 3,
            'updated_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('care-alerts'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('alerts')
                ->where('alerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'RISK REVIEW'
                        && !empty($alert['href'])
                        && str_contains((string) $alert['href'], 'falls-risk')
                        && ($alert['patientUrlKey'] ?? '') === 'pt-alert-link'
                )));
    }

    public function test_dashboard_care_alerts_include_mar_link_for_overdue_medication(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-mar-link',
            'slug' => 'pt-mar-link',
            'name' => 'MAR Link Patient',
            'status' => 'GREEN',
            'rag_status' => 'green',
            'staffing_ratio' => '1:1',
            'allergies' => ['None'],
            'avatar' => 'bg-slate-300',
        ]);

        \App\Models\PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Aspirin',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        \App\Models\MedicationReminder::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => \App\Models\PatientMedication::query()->where('patient_id', $patient->id)->value('id'),
            'due_at' => now()->subHour(),
            'dismissed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardStats.careAlerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'MISSED MEDICATION'
                        && !empty($alert['href'])
                        && str_contains((string) $alert['href'], 'pt-mar-link')
                        && str_contains((string) $alert['href'], 'today-mar')
                )));
    }
}
