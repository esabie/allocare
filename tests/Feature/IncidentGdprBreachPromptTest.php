<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentGdprBreachPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_submission_with_personal_data_suggests_gdpr_breach(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-incident-gdpr',
            'slug' => 'pt-incident-gdpr',
            'name' => 'GDPR Patient',
        ]);

        $payload = [
            'data' => array_merge([
                'incidentCategory' => 'safeguarding-concern',
                'incidentSubCategory' => 'Physical abuse',
                'severity' => 'high',
                'incidentTitle' => 'Records left visible',
                'incidentDate' => '2026-05-28',
                'incidentTime' => '14:30',
                'location' => 'Lounge',
                'reporterName' => 'Test Carer',
                'narrativeDescription' => 'Patient notes were visible to visitor.',
                'witnessDetails' => 'None recorded',
                'immediateActionsTaken' => 'Staff secured records',
                'injuriesSustained' => false,
                'medicalContactMade' => false,
                'familyNotified' => false,
                'socialWorkerNotified' => false,
                'safeguardingReferralSubmitted' => false,
                'riddorReportable' => false,
                'recurrencePrevention' => 'Review privacy',
                'correctiveActionsPlanned' => 'Training',
                'correctiveActionOwner' => 'Manager One',
                'managerName' => 'Manager One',
                'managerSignOff' => true,
                'staffMembers' => ['Test Carer'],
                'selectedImpacts' => ['Personal / confidential data'],
                'behaviour' => 'Patient notes were visible to visitor',
                'status' => 'Submitted',
            ]),
        ];

        $this->actingAs($user)
            ->post(route('form-snapshots.save', ['formKey' => 'incident:'.$patient->url_key]), $payload)
            ->assertRedirect(route('patients.incidents.create', $patient->url_key))
            ->assertSessionHas('suggest_gdpr_breach', true)
            ->assertSessionHas('gdprBreachPrefill.patient_id', $patient->id);

        $this->assertDatabaseHas('patient_incidents', [
            'patient_id' => $patient->id,
        ]);
    }
}
