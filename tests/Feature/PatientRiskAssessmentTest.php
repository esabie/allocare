<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientRiskAssessment;
use App\Models\PatientRiskAssessmentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRiskAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_risk_assessments(): void
    {
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-guest',
            'slug' => 'pt-risk-guest',
            'name' => 'Risk Guest',
        ]);

        $this->get(route('patients.risks', $patient->url_key))
            ->assertRedirect(route('login'));
    }

    public function test_carer_can_view_risk_assessment_list(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-view',
            'slug' => 'pt-risk-view',
            'name' => 'Risk View Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.risks', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientRiskAssessments')
                ->has('assessments', 6)
                ->where('assessments.0.slug', 'falls-risk'));
    }

    public function test_manager_can_save_risk_assessment(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-save',
            'slug' => 'pt-risk-save',
            'name' => 'Risk Save Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'risk_level' => 'high',
                'status' => 'active',
                'triggers' => 'History of falls, poor mobility',
                'current_controls' => '2:1 support, sensor mat',
                'mitigation_plan' => 'Physio referral, review footwear',
                'owner_name' => 'Nurse Alex',
                'last_reviewed_at' => now()->toDateString(),
                'review_cycle_months' => 3,
            ])
            ->assertRedirect(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']));

        $this->assertDatabaseHas('patient_risk_assessments', [
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
            'risk_level' => 'high',
            'status' => 'active',
            'owner_name' => 'Nurse Alex',
        ]);

        $this->assertDatabaseHas('patient_risk_assessment_versions', [
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
        ]);

        $assessment = PatientRiskAssessment::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($assessment->next_review_due_at);
        $this->assertTrue($assessment->next_review_due_at->greaterThan(now()));
    }

    public function test_care_worker_cannot_save_risk_assessment(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-deny',
            'slug' => 'pt-risk-deny',
            'name' => 'Risk Deny Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'risk_level' => 'high',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_overdue_risk_review_appears_on_dashboard(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-alert',
            'slug' => 'pt-risk-alert',
            'name' => 'Risk Alert Patient',
        ]);

        PatientRiskAssessment::query()->create([
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
            'risk_level' => 'high',
            'status' => 'active',
            'current_controls' => '2:1 support',
            'last_reviewed_at' => now()->subMonths(4)->toDateString(),
            'next_review_due_at' => now()->subDay()->toDateString(),
            'review_cycle_months' => 3,
            'updated_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('dashboardStats.careAlerts')
                ->where('dashboardStats.careAlerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'RISK REVIEW'
                        && str_contains((string) ($alert['details'] ?? ''), 'Falls Risk')
                        && !empty($alert['href'])
                )));
    }

    public function test_second_save_creates_additional_version(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-ver',
            'slug' => 'pt-risk-ver',
            'name' => 'Version Patient',
        ]);

        $payload = [
            'risk_level' => 'moderate',
            'status' => 'draft',
            'triggers' => 'Initial triggers',
            'current_controls' => 'Mat in place',
            'mitigation_plan' => 'Review weekly',
            'owner_name' => 'Nurse A',
            'last_reviewed_at' => now()->toDateString(),
            'review_cycle_months' => 3,
        ];

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $payload);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                ...$payload,
                'risk_level' => 'high',
            ]);

        $this->assertSame(2, \App\Models\PatientRiskAssessmentVersion::query()->where('patient_id', $patient->id)->count());
    }

    public function test_risk_assessment_pdf_download(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-pdf',
            'slug' => 'pt-risk-pdf',
            'name' => 'PDF Patient',
        ]);

        PatientRiskAssessment::query()->create([
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
            'risk_level' => 'high',
            'status' => 'active',
            'triggers' => 'Poor mobility',
            'current_controls' => '2:1 support',
            'mitigation_plan' => 'Physio',
            'owner_name' => 'Nurse B',
            'last_reviewed_at' => now()->toDateString(),
            'next_review_due_at' => now()->addMonths(3)->toDateString(),
            'review_cycle_months' => 3,
            'updated_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.risks.pdf', ['patient' => $patient->url_key, 'risk' => 'falls-risk']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_detail_page_includes_version_history(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-hist',
            'slug' => 'pt-risk-hist',
            'name' => 'History Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'risk_level' => 'moderate',
                'status' => 'active',
                'triggers' => 'Test',
                'current_controls' => 'Control',
                'mitigation_plan' => 'Plan',
                'owner_name' => 'Owner',
                'last_reviewed_at' => now()->toDateString(),
                'review_cycle_months' => 3,
            ]);

        $this->actingAs($user)
            ->get(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canExportPdf', true)
                ->has('versions', 1)
                ->where('versions.0.changeSummary', 'Initial assessment recorded'));
    }

    public function test_manager_can_restore_risk_assessment_from_version(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-restore',
            'slug' => 'pt-risk-restore',
            'name' => 'Restore Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'risk_level' => 'moderate',
                'status' => 'draft',
                'triggers' => 'Original triggers',
                'current_controls' => 'Original controls',
                'mitigation_plan' => 'Original plan',
                'owner_name' => 'Owner A',
                'last_reviewed_at' => now()->toDateString(),
                'review_cycle_months' => 3,
            ]);

        $version = PatientRiskAssessmentVersion::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($version);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'risk_level' => 'high',
                'status' => 'active',
                'triggers' => 'Updated triggers',
                'current_controls' => 'Updated controls',
                'mitigation_plan' => 'Updated plan',
                'owner_name' => 'Owner B',
                'last_reviewed_at' => now()->toDateString(),
                'review_cycle_months' => 3,
            ]);

        $this->actingAs($user)
            ->postJson(route('patients.risks.restore-version', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'version_id' => $version->id,
            ])
            ->assertRedirect(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']));

        $assessment = PatientRiskAssessment::query()->where('patient_id', $patient->id)->first();
        $this->assertSame('moderate', $assessment->risk_level);
        $this->assertSame('Original triggers', $assessment->triggers);
        $this->assertGreaterThanOrEqual(3, PatientRiskAssessmentVersion::query()->where('patient_id', $patient->id)->count());
    }
}
