<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientCarePlanModule;
use App\Models\PatientCarePlanSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarePlanReviewTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function safeguardingPayload(array $overrides = []): array
    {
        return array_merge([
            'what_matters_to_me' => 'Dignity and safety at home.',
            'baseline_clinical_summary' => 'Requires safeguarding oversight.',
            'linked_risks_rag' => 'Safeguarding concern',
            'smart_outcomes' => 'Remain safe in the community.',
            'proactive_support' => 'Structured visits and observation.',
            'active_steps' => 'Follow safeguarding plan.',
            'reactive_steps' => 'Contact safeguarding lead.',
            'equipment_required' => 'None',
            'staff_competencies_training_required' => 'Safeguarding level 2',
            'monitoring_and_recording' => 'Daily notes',
            'escalation_pathway' => 'On-call manager then MASH',
            'capacity_consent_note' => 'Best interest process documented.',
            'review_due' => now()->addMonths(6)->toDateString(),
            'owner' => 'Care Manager',
            'primary_focus_0' => 'Previous MARAC involvement',
            'primary_focus_1' => 'Current risks',
            'primary_focus_2' => 'Agency contacts',
            'primary_focus_3' => 'Recording requirements',
            'primary_focus_4' => 'Review schedule',
        ], $overrides);
    }

    public function test_new_care_plan_includes_default_review_date_twelve_months_out(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-review-default',
            'slug' => 'pt-review-default',
            'name' => 'Review Default Patient',
            'care_plan_modules_initialized' => true,
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'safeguarding',
            'sort_order' => 0,
            'activated_at' => now(),
        ]);

        $expected = now()->addMonths(12)->toDateString();

        $this->actingAs($user)
            ->get(route('patients.careplans.show', [
                'patient' => $patient->url_key,
                'plan' => 'safeguarding',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reviewPolicy.defaultDueDate', $expected)
                ->where('initialSnapshot.review_due', $expected));
    }

    public function test_save_rejects_review_date_beyond_twelve_months(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-review-max',
            'slug' => 'pt-review-max',
            'name' => 'Review Max Patient',
            'care_plan_modules_initialized' => true,
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'safeguarding',
            'sort_order' => 0,
            'activated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('patients.careplans.save', [
                'patient' => $patient->url_key,
                'plan' => 'safeguarding',
            ]), [
                'data' => $this->safeguardingPayload([
                    'review_due' => now()->addMonths(13)->toDateString(),
                ]),
            ])
            ->assertStatus(422);
    }

    public function test_overdue_care_plan_review_appears_on_dashboard(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-review-overdue',
            'slug' => 'pt-review-overdue',
            'name' => 'Overdue Review Patient',
        ]);

        PatientCarePlanSummary::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
            'schema_version' => 2,
            'status' => 'submitted',
            'submitted_at' => now()->subMonths(13),
            'review_due_at' => now()->subDays(3)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardStats.careAlerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'CARE PLAN REVIEW OVERDUE'
                        && str_contains($alert['details'] ?? '', 'Safeguarding')
                )));
    }

    public function test_due_soon_care_plan_review_appears_on_dashboard(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-review-soon',
            'slug' => 'pt-review-soon',
            'name' => 'Due Soon Review Patient',
        ]);

        PatientCarePlanSummary::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'mobility-and-moving',
            'schema_version' => 2,
            'status' => 'submitted',
            'submitted_at' => now()->subMonths(11),
            'review_due_at' => now()->addDays(14)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardStats.careAlerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'CARE PLAN REVIEW DUE'
                        && str_contains($alert['details'] ?? '', 'Mobility')
                )));
    }
}
