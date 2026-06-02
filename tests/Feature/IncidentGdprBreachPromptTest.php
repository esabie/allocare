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
            'data' => [
                'status' => 'Submitted',
                'incidentTitle' => 'Records left visible',
                'selectedImpacts' => ['Personal / confidential data'],
                'behaviour' => 'Patient notes were visible to visitor',
                'consequence' => 'Staff secured records',
                'immediateOutcome' => 'No further exposure',
                'lessonsLearnt' => 'Review privacy',
                'actionsPlanned' => 'Training',
                'managerName' => 'Manager One',
                'managerSignOff' => true,
            ],
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
