<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientObservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_observations_page(): void
    {
        $this->get(route('patients.observations', 'pt-obs-1'))->assertRedirect(route('login'));
    }

    public function test_staff_can_view_observations_page(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-1',
            'slug' => 'pt-obs-1',
            'name' => 'Test Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.observations', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PatientObservations'));
    }

    public function test_staff_can_record_clinical_observation(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-2',
            'slug' => 'pt-obs-2',
            'name' => 'Jane Patient',
        ]);

        $this->actingAs($user)
            ->from(route('patients.observations', $patient->url_key))
            ->post(route('patients.vitals.store', $patient->url_key), [
                'heart_rate' => 72,
                'bp_systolic' => 120,
                'spo2' => 98,
                'other_observation' => 'Patient alert and mobilising with frame.',
            ])
            ->assertRedirect(route('patients.observations', $patient->url_key));

        $this->assertDatabaseHas('patient_vitals', [
            'patient_id' => $patient->id,
            'heart_rate' => 72,
            'bp_systolic' => 120,
            'spo2' => 98,
            'other_observation' => 'Patient alert and mobilising with frame.',
            'recorded_by_user_id' => $user->id,
        ]);
    }

    public function test_observations_are_listed_newest_first(): void
    {
        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-3',
            'slug' => 'pt-obs-3',
            'name' => 'John Patient',
        ]);

        PatientVital::query()->create([
            'patient_id' => $patient->id,
            'heart_rate' => 60,
            'bp_systolic' => 110,
            'spo2' => 97,
            'recorded_at' => now()->subHour(),
            'recorded_by_user_id' => $user->id,
        ]);

        PatientVital::query()->create([
            'patient_id' => $patient->id,
            'heart_rate' => 80,
            'bp_systolic' => 130,
            'spo2' => 99,
            'recorded_at' => now(),
            'recorded_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.observations', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientObservations')
                ->where('observations.0.heartRate', 80)
                ->where('observations.1.heartRate', 60));
    }
}
