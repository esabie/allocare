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

    /** @var list<string> UK regulated community care standard risk assessment slugs */
    private const UK_STANDARD_RISK_SLUGS = [
        'clinical-rationale',
        'falls-risk',
        'medication-risk',
        'aspiration-risk',
        'skin-integrity',
        'behaviour-support-risk',
        'moving-handling-risk',
        'infection-risk',
        'diabetes-management-risk',
        'epilepsy-seizure-risk',
        'respiratory-risk',
        'environmental-risk',
        'safeguarding-risk',
        'community-access-risk',
        'lone-worker-risk',
        'elopement-risk',
        'infection-outbreak-risk',
    ];

    /** @return array<string, mixed> */
    private function structuredRiskPayload(array $overrides = []): array
    {
        return array_merge([
            'risk_level' => 'amber',
            'status' => 'draft',
            'risk_statement' => 'Risk of falls due to poor mobility and cognitive impairment.',
            'triggers' => 'Uneven flooring, rushing to toilet, night-time confusion',
            'proactive_controls' => 'Physio referral, footwear review, clutter removal',
            'active_controls' => '2:1 support on transfers, sensor mat in bedroom',
            'reactive_controls' => 'Post-fall protocol, GP notification within 24 hours',
            'monitoring_requirements' => 'Daily mobility observation; formal review every 3 months',
            'escalation_pathway' => 'On-call manager → GP → 111/999 as indicated',
            'capacity_consent_notes' => 'Has capacity to consent to falls prevention plan',
            'legal_restrictions' => 'No DoLS authorisation in place',
            'owner_name' => 'Nurse Test',
            'last_reviewed_at' => now()->toDateString(),
            'review_cycle_months' => 3,
        ], $overrides);
    }

    public function test_uk_standard_risk_assessment_templates_are_configured(): void
    {
        $templates = risk_assessment_templates();
        $slugs = array_column($templates, 'slug');

        $this->assertSame(self::UK_STANDARD_RISK_SLUGS, $slugs);
        $this->assertCount(17, $templates);

        foreach ($templates as $template) {
            $this->assertNotEmpty($template['title']);
            $this->assertNotEmpty($template['suggestedControls']);
        }
    }

    public function test_manager_can_save_each_uk_standard_risk_assessment(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-all',
            'slug' => 'pt-risk-all',
            'name' => 'All Risks Patient',
        ]);

        foreach (self::UK_STANDARD_RISK_SLUGS as $slug) {
            $this->actingAs($user)
                ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => $slug]), $this->structuredRiskPayload())
                ->assertRedirect(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => $slug]));
        }

        $this->assertSame(17, PatientRiskAssessment::query()->where('patient_id', $patient->id)->count());
    }

    public function test_manager_can_save_structured_risk_assessment_fields(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-structured',
            'slug' => 'pt-risk-structured',
            'name' => 'Structured Risk Patient',
        ]);

        $payload = $this->structuredRiskPayload([
            'status' => 'active',
            'risk_level' => 'red',
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $payload)
            ->assertRedirect(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']));

        $this->assertDatabaseHas('patient_risk_assessments', [
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
            'risk_level' => 'red',
            'risk_statement' => $payload['risk_statement'],
            'proactive_controls' => $payload['proactive_controls'],
            'active_controls' => $payload['active_controls'],
            'reactive_controls' => $payload['reactive_controls'],
            'monitoring_requirements' => $payload['monitoring_requirements'],
            'escalation_pathway' => $payload['escalation_pathway'],
            'capacity_consent_notes' => $payload['capacity_consent_notes'],
            'legal_restrictions' => $payload['legal_restrictions'],
        ]);
    }

    public function test_active_risk_assessment_requires_owner_and_review_date(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-required',
            'slug' => 'pt-risk-required',
            'name' => 'Required Fields Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'risk_level' => 'amber',
                'status' => 'active',
            ])
            ->assertSessionHasErrors(['owner_name', 'last_reviewed_at']);
    }

    public function test_legacy_rag_values_are_normalized_on_save(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-legacy',
            'slug' => 'pt-risk-legacy',
            'name' => 'Legacy RAG Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $this->structuredRiskPayload([
                'risk_level' => 'high',
            ]));

        $this->assertSame('red', PatientRiskAssessment::query()->where('patient_id', $patient->id)->value('risk_level'));
    }

    public function test_new_risk_assessment_defaults_rag_from_patient_profile(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-rag',
            'slug' => 'pt-risk-rag',
            'name' => 'RAG Default Patient',
            'rag_status' => 'red',
        ]);

        $this->actingAs($user)
            ->get(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('patient.ragStatus', 'red')
                ->where('patient.ragStatusLabel', 'Red')
                ->where('assessment.riskLevel', null)
                ->where('assessment.hasRecord', false));

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $this->structuredRiskPayload([
                'risk_level' => 'red',
                'status' => 'active',
            ]));

        $this->assertSame('red', PatientRiskAssessment::query()->where('patient_id', $patient->id)->value('risk_level'));
    }

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
                ->has('assessments', 17)
                ->where('assessments.0.slug', 'clinical-rationale'));
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
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $this->structuredRiskPayload([
                'risk_level' => 'red',
                'status' => 'active',
            ]))
            ->assertRedirect(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']));

        $this->assertDatabaseHas('patient_risk_assessments', [
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
            'risk_level' => 'red',
            'status' => 'active',
            'owner_name' => 'Nurse Test',
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
                'risk_level' => 'red',
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
            'risk_level' => 'red',
            'status' => 'active',
            'active_controls' => '2:1 support',
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
                    fn ($alert) => ($alert['label'] ?? '') === 'RISK REVIEW MISSED'
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

        $payload = $this->structuredRiskPayload();

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $payload);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                ...$payload,
                'risk_level' => 'red',
            ]);

        $this->assertSame(2, PatientRiskAssessmentVersion::query()->where('patient_id', $patient->id)->count());
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
            'risk_level' => 'red',
            'status' => 'active',
            'risk_statement' => 'Falls risk in community setting',
            'triggers' => 'Poor mobility',
            'active_controls' => '2:1 support',
            'escalation_pathway' => 'Call manager',
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
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $this->structuredRiskPayload([
                'status' => 'active',
            ]));

        $this->actingAs($user)
            ->get(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canExportPdf', true)
                ->has('versions', 1)
                ->where('versions.0.changeSummary', 'Initial assessment recorded')
                ->where('assessment.riskStatement', 'Risk of falls due to poor mobility and cognitive impairment.')
                ->where('assessment.riskLevelLabel', 'Amber'));
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
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $this->structuredRiskPayload([
                'triggers' => 'Original triggers',
                'active_controls' => 'Original controls',
            ]));

        $version = PatientRiskAssessmentVersion::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($version);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $this->structuredRiskPayload([
                'risk_level' => 'red',
                'status' => 'active',
                'triggers' => 'Updated triggers',
                'active_controls' => 'Updated controls',
                'owner_name' => 'Owner B',
            ]));

        $this->actingAs($user)
            ->postJson(route('patients.risks.restore-version', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                'version_id' => $version->id,
            ])
            ->assertRedirect(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']));

        $assessment = PatientRiskAssessment::query()->where('patient_id', $patient->id)->first();
        $this->assertSame('amber', $assessment->risk_level);
        $this->assertSame('Original triggers', $assessment->triggers);
        $this->assertGreaterThanOrEqual(3, PatientRiskAssessmentVersion::query()->where('patient_id', $patient->id)->count());
    }

    public function test_manager_can_link_risk_assessment_to_care_plans_and_incidents(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-links',
            'slug' => 'pt-risk-links',
            'name' => 'Linked Risk Patient',
        ]);

        $incident = \App\Models\PatientIncident::query()->create([
            'patient_id' => $patient->id,
            'reported_by_user_id' => $user->id,
            'reference' => 'INC-LINK-01',
            'incident_title' => 'Fall in lounge',
            'incident_date' => now()->subDays(3)->toDateString(),
            'data' => ['type' => 'fall'],
            'submitted_at' => now(),
        ]);

        $payload = $this->structuredRiskPayload([
            'status' => 'active',
            'linked_care_plan_slugs' => ['mobility-and-moving', 'personal-care-and-dignity'],
            'linked_incident_ids' => [$incident->id],
        ]);

        $this->actingAs($user)
            ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), $payload)
            ->assertRedirect();

        $assessment = PatientRiskAssessment::query()->where('patient_id', $patient->id)->first();
        $this->assertSame(['mobility-and-moving', 'personal-care-and-dignity'], $assessment->linked_care_plan_slugs);
        $this->assertSame([$incident->id], $assessment->linked_incident_ids);

        $this->actingAs($user)
            ->get(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('assessment.linkedCarePlanSlugs', ['mobility-and-moving', 'personal-care-and-dignity'])
                ->where('assessment.linkedIncidentIds', [$incident->id])
                ->has('assessment.linkedIncidents', 1));
    }

    public function test_risk_version_history_is_not_truncated(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-versions',
            'slug' => 'pt-risk-versions',
            'name' => 'Version History Patient',
        ]);

        $payload = $this->structuredRiskPayload(['status' => 'active']);

        for ($i = 0; $i < 25; $i++) {
            $this->actingAs($user)
                ->post(route('patients.risks.save', ['patient' => $patient->url_key, 'risk' => 'falls-risk']), [
                    ...$payload,
                    'risk_statement' => 'Version statement '.$i,
                ]);
        }

        $this->assertSame(25, PatientRiskAssessmentVersion::query()->where('patient_id', $patient->id)->count());

        $this->actingAs($user)
            ->get(route('patients.risks.show', ['patient' => $patient->url_key, 'risk' => 'falls-risk']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('versions', 25));
    }

    public function test_full_risk_assessment_pack_pdf_export(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-pack',
            'slug' => 'pt-risk-pack',
            'name' => 'Pack Export Patient',
        ]);

        PatientRiskAssessment::query()->create([
            'patient_id' => $patient->id,
            'risk_slug' => 'falls-risk',
            'risk_level' => 'red',
            'status' => 'active',
            'risk_statement' => 'Falls risk',
            'active_controls' => '2:1 support',
            'owner_name' => 'Nurse C',
            'last_reviewed_at' => now()->toDateString(),
            'next_review_due_at' => now()->addMonths(3)->toDateString(),
            'review_cycle_months' => 3,
            'updated_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.risks.export.pdf', $patient->url_key))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_missed_risk_review_generates_patient_profile_alert(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-risk-profile-alert',
            'slug' => 'pt-risk-profile-alert',
            'name' => 'Profile Alert Patient',
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
            ->get(route('patients.show', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeAlerts', fn ($messages) => collect($messages)->contains(
                    fn ($message) => str_contains((string) $message, 'Risk review missed')
                        && str_contains((string) $message, 'Falls Risk')
                )));
    }
}
