<?php

namespace Tests\Feature;

use App\Models\IncidentInvestigation;
use App\Models\Patient;
use App\Models\PatientIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentInvestigationTest extends TestCase
{
    use RefreshDatabase;

    private function compliantIncidentPayload(array $overrides = []): array
    {
        return array_merge([
            'incidentCategory' => 'accident',
            'incidentSubCategory' => 'Fall',
            'severity' => 'medium',
            'incidentTitle' => 'Fall in lounge',
            'incidentDate' => '2026-05-28',
            'incidentTime' => '14:30',
            'location' => 'Communal lounge',
            'reporterName' => 'Test Carer',
            'narrativeDescription' => 'Patient fell while standing from their chair.',
            'witnessDetails' => 'No independent witnesses present.',
            'immediateActionsTaken' => 'Staff assisted patient back to chair and monitored for injury.',
            'injuriesSustained' => false,
            'medicalContactMade' => false,
            'familyNotified' => true,
            'familyNotifiedAt' => '2026-05-28T15:00',
            'socialWorkerNotified' => false,
            'safeguardingReferralSubmitted' => false,
            'riddorReportable' => false,
            'recurrencePrevention' => 'Review mobility plan and walking aid before transfers.',
            'correctiveActionsPlanned' => 'Update risk assessment and offer snack before meals.',
            'correctiveActionOwner' => 'Care Manager',
            'managerName' => 'Jane Manager',
            'managerSignOff' => true,
            'staffMembers' => ['Test Carer'],
            'status' => 'Submitted',
        ], $overrides);
    }

    public function test_submitting_incident_form_creates_patient_incident_and_investigation(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-inc-1', 'slug' => 'pt-inc-1', 'name' => 'Incident Patient']);

        $payload = $this->compliantIncidentPayload();

        $this->actingAs($user)
            ->post(route('form-snapshots.save', 'incident:'.$patient->url_key), ['data' => $payload])
            ->assertRedirect(route('patients.incidents.create', $patient->url_key))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('patient_incidents', [
            'patient_id' => $patient->id,
            'incident_title' => 'Fall in lounge',
            'incident_category' => 'accident',
            'severity' => 'medium',
            'sub_category' => 'Fall',
        ]);

        $incident = PatientIncident::query()->first();
        $this->assertNotNull($incident->investigation);
        $this->assertSame(IncidentInvestigation::STATUS_PENDING, $incident->investigation->investigation_status);
        $this->assertSame('Care Manager', $incident->investigation->corrective_action_owner);
    }

    public function test_manager_can_update_investigation_with_riddor_and_safeguarding(): void
    {
        $manager = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-inv', 'slug' => 'pt-inv', 'name' => 'Investigation Patient']);

        $incident = PatientIncident::query()->create([
            'patient_id' => $patient->id,
            'reference' => 'INC-2026-0001',
            'incident_title' => 'Test incident',
            'data' => ['incidentTitle' => 'Test incident'],
            'submitted_at' => now()->subDay(),
        ]);

        $investigation = IncidentInvestigation::query()->create([
            'patient_incident_id' => $incident->id,
            'investigation_status' => IncidentInvestigation::STATUS_PENDING,
            'due_at' => now()->addDays(5),
        ]);

        $this->actingAs($manager)
            ->patch(route('reports.incidents.investigation.update', $incident), [
                'investigation_status' => IncidentInvestigation::STATUS_IN_PROGRESS,
                'investigation_summary' => 'Initial review completed.',
                'investigation_outcome' => 'No further action required beyond care plan update.',
                'corrective_action_owner' => 'Registered Manager',
                'recurrence_prevention' => 'Additional supervision during transfers.',
                'riddor_reportable' => true,
                'riddor_category' => 'over_7_day_incapacity',
                'safeguarding_concern' => true,
                'safeguarding_referral_made' => true,
                'safeguarding_authority' => 'Local Authority',
                'safeguarding_reference' => 'SG-123',
            ])
            ->assertRedirect(route('reports.incidents.show', $incident));

        $investigation->refresh();
        $this->assertSame(IncidentInvestigation::STATUS_IN_PROGRESS, $investigation->investigation_status);
        $this->assertTrue($investigation->riddor_reportable);
        $this->assertTrue($investigation->safeguarding_referral_made);
        $this->assertSame('No further action required beyond care plan update.', $investigation->investigation_outcome);
        $this->assertSame('Registered Manager', $investigation->corrective_action_owner);
    }

    public function test_incidents_report_lists_patient_incidents(): void
    {
        $manager = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-list', 'slug' => 'pt-list', 'name' => 'List Patient']);

        $incident = PatientIncident::query()->create([
            'patient_id' => $patient->id,
            'reference' => 'INC-2026-0099',
            'incident_title' => 'Listed incident',
            'data' => [],
            'submitted_at' => now(),
        ]);

        IncidentInvestigation::query()->create([
            'patient_incident_id' => $incident->id,
            'investigation_status' => IncidentInvestigation::STATUS_PENDING,
            'riddor_reportable' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('reports.incidents'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ReportsIncidents')
                ->has('incidents.data', 1)
                ->where('incidents.data.0.reference', 'INC-2026-0099')
                ->where('stats.riddorOpen', 1));

        $this->actingAs($manager)
            ->get(route('reports.incidents.export.riddor'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_care_worker_cannot_update_investigation(): void
    {
        $worker = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-deny', 'slug' => 'pt-deny', 'name' => 'Deny Patient']);

        $incident = PatientIncident::query()->create([
            'patient_id' => $patient->id,
            'reference' => 'INC-2026-0002',
            'data' => [],
            'submitted_at' => now(),
        ]);

        IncidentInvestigation::query()->create([
            'patient_incident_id' => $incident->id,
            'investigation_status' => IncidentInvestigation::STATUS_PENDING,
        ]);

        $this->actingAs($worker)
            ->patch(route('reports.incidents.investigation.update', $incident), [
                'investigation_status' => IncidentInvestigation::STATUS_COMPLETED,
            ])
            ->assertForbidden();
    }

    public function test_required_incident_categories_are_configured(): void
    {
        $categories = incident_categories();
        $slugs = array_column($categories, 'slug');

        $this->assertSame([
            'accident',
            'near-miss',
            'medication-error',
            'safeguarding-concern',
            'infection-control',
            'equipment-failure',
            'behaviour-incident',
            'missing-person',
            'complaint',
            'compliment',
        ], $slugs);

        foreach ($categories as $category) {
            $this->assertNotEmpty($category['label']);
            $this->assertNotEmpty($category['examples']);
            $this->assertNotEmpty($category['subcategories']);
        }
    }

    public function test_incident_submission_requires_valid_category(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-inc-cat', 'slug' => 'pt-inc-cat', 'name' => 'Category Patient']);

        $this->actingAs($user)
            ->post(route('form-snapshots.save', 'incident:'.$patient->url_key), [
                'data' => $this->compliantIncidentPayload([
                    'incidentCategory' => 'not-a-real-category',
                ]),
            ])
            ->assertSessionHasErrors(['incidentCategory']);

        $this->assertDatabaseCount('patient_incidents', 0);
    }

    public function test_incident_submission_requires_compliance_fields(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-inc-req', 'slug' => 'pt-inc-req', 'name' => 'Required Fields Patient']);

        $this->actingAs($user)
            ->post(route('form-snapshots.save', 'incident:'.$patient->url_key), [
                'data' => [
                    'incidentTitle' => 'Incomplete incident',
                    'incidentCategory' => 'accident',
                    'status' => 'Submitted',
                ],
            ])
            ->assertSessionHasErrors(['severity', 'narrativeDescription']);

        $this->assertDatabaseCount('patient_incidents', 0);
    }

    public function test_care_worker_can_submit_incident_without_manager_sign_off(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-inc-worker', 'slug' => 'pt-inc-worker', 'name' => 'Worker Patient']);

        $payload = $this->compliantIncidentPayload([
            'managerName' => null,
            'managerSignOff' => false,
        ]);

        $this->actingAs($user)
            ->post(route('form-snapshots.save', 'incident:'.$patient->url_key), ['data' => $payload])
            ->assertRedirect(route('patients.incidents.create', $patient->url_key));

        $this->assertDatabaseHas('patient_incidents', [
            'patient_id' => $patient->id,
            'incident_title' => 'Fall in lounge',
        ]);
    }

    public function test_supervisor_can_view_incidents_but_not_export_reports(): void
    {
        $supervisor = User::factory()->create(['primary_role' => 'supervisor', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-sup', 'slug' => 'pt-sup', 'name' => 'Supervisor Patient']);

        PatientIncident::query()->create([
            'patient_id' => $patient->id,
            'reference' => 'INC-2026-0200',
            'incident_title' => 'Supervisor visible incident',
            'data' => [],
            'submitted_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('reports.incidents'))
            ->assertOk();

        $this->actingAs($supervisor)
            ->get(route('reports'))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('reports.incidents.export.riddor'))
            ->assertForbidden();
    }

    public function test_supervisor_can_escalate_but_not_sign_off_investigation(): void
    {
        $supervisor = User::factory()->create(['primary_role' => 'supervisor', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-sup-inv', 'slug' => 'pt-sup-inv', 'name' => 'Supervisor Inv Patient']);

        $incident = PatientIncident::query()->create([
            'patient_id' => $patient->id,
            'reference' => 'INC-2026-0201',
            'data' => [],
            'submitted_at' => now(),
        ]);

        IncidentInvestigation::query()->create([
            'patient_incident_id' => $incident->id,
            'investigation_status' => IncidentInvestigation::STATUS_PENDING,
        ]);

        $this->actingAs($supervisor)
            ->patch(route('reports.incidents.investigation.update', $incident), [
                'investigation_status' => IncidentInvestigation::STATUS_IN_PROGRESS,
                'investigation_summary' => 'Supervisor review started.',
            ])
            ->assertRedirect(route('reports.incidents.show', $incident));

        $this->actingAs($supervisor)
            ->patch(route('reports.incidents.investigation.update', $incident), [
                'investigation_status' => IncidentInvestigation::STATUS_COMPLETED,
            ])
            ->assertForbidden();
    }

    public function test_incidents_report_lists_category(): void
    {
        $manager = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-inc-cat-list', 'slug' => 'pt-inc-cat-list', 'name' => 'Category List Patient']);

        PatientIncident::query()->create([
            'patient_id' => $patient->id,
            'reference' => 'INC-2026-0101',
            'incident_title' => 'Medication given late',
            'incident_category' => 'medication-error',
            'severity' => 'high',
            'data' => ['incidentCategory' => 'medication-error', 'severity' => 'high'],
            'submitted_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('reports.incidents'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('incidents.data.0.categoryLabel', 'Medication Error')
                ->where('incidents.data.0.category', 'medication-error')
                ->where('incidents.data.0.severityLabel', 'High'));
    }
}
