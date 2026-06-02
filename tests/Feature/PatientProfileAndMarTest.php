<?php

namespace Tests\Feature;

use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientProfileAndMarTest extends TestCase
{
    use RefreshDatabase;

    private function createPatient(array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'url_key' => 'ac-'.random_int(10000, 99999),
            'slug' => 'test-patient',
            'name' => 'Test Patient',
            'nhs_number' => str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'status' => 'GREEN',
            'rag_status' => 'green',
            'staffing_ratio' => '1:1 Support',
            'allergies' => ['None'],
            'avatar' => 'bg-slate-300',
        ], $overrides));
    }

    public function test_care_manager_can_update_patient_clinical_profile(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient([
            'url_key' => 'ac-12345',
            'capacity_status' => null,
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.profile.update', $patient->url_key), [
                'gp_name' => 'Dr Smith',
                'gp_practice' => 'Riverside Surgery',
                'capacity_status' => 'Has capacity',
                'allergy_details' => [
                    [
                        'allergen' => 'Penicillin',
                        'reaction' => 'Rash',
                        'severity' => 'Severe',
                        'verified_at' => '2026-01-15',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $patient->refresh();
        $this->assertSame('Dr Smith', $patient->gp_name);
        $this->assertSame('Has capacity', $patient->capacity_status);
        $this->assertCount(1, $patient->allergy_details);
    }

    public function test_mar_save_accepts_delayed_status_with_reason(): void
    {
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-99999']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Paracetamol',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($carer)
            ->post(route('patients.mar.save', ['patient' => $patient->url_key, 'mar' => 'today-mar']), [
                'rows' => [
                    [
                        'id' => $medication->id,
                        'medicine' => 'Paracetamol',
                        'time' => '08:00',
                        'route' => 'Oral',
                        'dose' => '500mg',
                        'status' => 'Delayed',
                        'reason' => 'Service user asleep',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('medication_administrations', [
            'patient_medication_id' => $medication->id,
            'status' => 'delayed',
            'reason' => 'Service user asleep',
        ]);
    }

    public function test_monthly_mar_chart_pdf_downloads(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-55555']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Aspirin',
            'active' => true,
            'scheduled_time' => '09:00:00',
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'status' => 'given',
            'administered_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('patients.mar.monthly-chart.pdf', ['patient' => $patient->url_key]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_manager_can_clear_todays_mar(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-clear-mar']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Warfarin 5mg',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        MedicationAdministration::query()->create([
            'patient_id' => $patient->id,
            'patient_medication_id' => $medication->id,
            'administered_by_user_id' => $manager->id,
            'status' => 'given',
            'administered_at' => now(),
            'source_mar_slug' => 'today-mar',
        ]);

        $this->actingAs($manager)
            ->post(route('patients.mar.clear-today', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']));

        $this->assertDatabaseMissing('medication_administrations', [
            'patient_medication_id' => $medication->id,
        ]);

        $this->actingAs($manager)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initialRows.0.status', 'Due')
                ->where('initialRows.0.by', '-'));
    }

    public function test_manager_can_deactivate_medication(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-deactivate-med']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Metformin 500mg',
            'active' => true,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($manager)
            ->post(route('patients.medications.deactivate', [
                'patient' => $patient->url_key,
                'medication' => $medication->id,
            ]), ['mar' => 'today-mar'])
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']));

        $this->assertFalse($medication->fresh()->active);

        $this->actingAs($manager)
            ->get(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initialRows', [])
                ->has('inactiveMedications', 1));

        $this->actingAs($manager)
            ->post(route('patients.medications.reactivate', [
                'patient' => $patient->url_key,
                'medication' => $medication->id,
            ]), ['mar' => 'today-mar'])
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']));

        $this->assertTrue($medication->fresh()->active);
    }

    public function test_deactivating_already_inactive_medication_redirects_gracefully(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-already-inactive']);
        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'name' => 'Old Medication',
            'active' => false,
            'scheduled_time' => '08:00:00',
        ]);

        $this->actingAs($manager)
            ->post(route('patients.medications.deactivate', [
                'patient' => $patient->url_key,
                'medication' => $medication->id,
            ]), ['mar' => 'today-mar'])
            ->assertRedirect(route('patients.mar.show', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertSessionHas('success');
    }

    public function test_medication_store_accepts_json_for_offline_sync(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
        $patient = $this->createPatient(['url_key' => 'ac-med-json']);

        $this->actingAs($manager)
            ->postJson(route('patients.medications.store', ['patient' => $patient->url_key]), [
                'name' => 'Ibuprofen',
                'route' => 'Oral',
                'dose' => '200mg',
                'frequency' => 'once_daily',
                'is_prn' => false,
                'is_controlled' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patient_medications', [
            'patient_id' => $patient->id,
            'name' => 'Ibuprofen',
            'active' => true,
        ]);
    }

    public function test_care_worker_cannot_clear_todays_mar(): void
    {
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-deny-clear']);

        $this->actingAs($carer)
            ->post(route('patients.mar.clear-today', ['patient' => $patient->url_key, 'mar' => 'today-mar']))
            ->assertForbidden();
    }
}
