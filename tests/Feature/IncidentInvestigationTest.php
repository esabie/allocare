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

    public function test_submitting_incident_form_creates_patient_incident_and_investigation(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create(['url_key' => 'pt-inc-1', 'slug' => 'pt-inc-1', 'name' => 'Incident Patient']);

        $payload = [
            'incidentTitle' => 'Fall in lounge',
            'incidentDate' => '2026-05-28',
            'incidentTime' => '14:30',
            'location' => 'Communal lounge',
            'reporterName' => 'Test Carer',
            'antecedent' => 'Patient became agitated before lunch.',
            'behaviour' => 'Stood abruptly and lost balance.',
            'consequence' => 'Staff assisted to chair.',
            'immediateOutcome' => 'No injury observed.',
            'lessonsLearnt' => 'Monitor before meals.',
            'newTriggers' => 'Hunger',
            'actionsPlanned' => 'Offer snack earlier.',
            'status' => 'Submitted',
        ];

        $this->actingAs($user)
            ->post(route('form-snapshots.save', 'incident:'.$patient->url_key), ['data' => $payload])
            ->assertRedirect(route('patients.incidents.create', $patient->url_key))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('patient_incidents', [
            'patient_id' => $patient->id,
            'incident_title' => 'Fall in lounge',
        ]);

        $incident = PatientIncident::query()->first();
        $this->assertNotNull($incident->investigation);
        $this->assertSame(IncidentInvestigation::STATUS_PENDING, $incident->investigation->investigation_status);
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
                ->has('incidents', 1)
                ->where('incidents.0.reference', 'INC-2026-0099')
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
}
