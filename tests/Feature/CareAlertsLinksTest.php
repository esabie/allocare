<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientRiskAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
            'risk_level' => 'red',
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
                    fn ($alert) => ($alert['label'] ?? '') === 'RISK REVIEW MISSED'
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

    public function test_missed_medication_alert_not_shown_when_slot_already_given(): void
    {
        Carbon::setTestNow('2026-06-20 10:45:00');
        Cache::forget('medication_escalations_last_run');

        $carer = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $admin = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-given-alert',
            'slug' => 'pt-given-alert',
            'name' => 'Given Alert Patient',
            'status' => 'GREEN',
            'rag_status' => 'green',
            'staffing_ratio' => '1:1',
            'allergies' => ['None'],
            'avatar' => 'bg-slate-300',
        ]);

        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Panadol (Paracetamol)',
            'dose' => '500 mg',
            'active' => true,
            'scheduled_times' => ['08:00'],
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [[
                    'id' => $medication->id,
                    'medicine' => 'Panadol (Paracetamol)',
                    'time' => '08:00',
                    'route' => 'Oral',
                    'dose' => '500 mg',
                    'status' => 'Given',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($carer)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reminders', fn ($reminders) => collect($reminders)->isEmpty())
                ->where('initialRows.0.status', 'Given'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardStats.careAlerts', fn ($alerts) => ! collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'MISSED MEDICATION'
                        && ($alert['patientUrlKey'] ?? '') === $patient->url_key
                )));
    }
}
