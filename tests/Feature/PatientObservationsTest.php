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
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-1',
            'slug' => 'pt-obs-1',
            'name' => 'Test Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.observations', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientObservations')
                ->has('chartData.series'));
    }

    public function test_observations_page_includes_chart_series_for_recorded_vitals(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-chart',
            'slug' => 'pt-obs-chart',
            'name' => 'Chart Patient',
        ]);

        PatientVital::query()->create([
            'patient_id' => $patient->id,
            'heart_rate' => 70,
            'bp_systolic' => 118,
            'spo2' => 97,
            'recorded_at' => now()->subDays(2),
            'recorded_by_user_id' => $user->id,
        ]);
        PatientVital::query()->create([
            'patient_id' => $patient->id,
            'heart_rate' => 76,
            'bp_systolic' => 122,
            'spo2' => 98,
            'recorded_at' => now()->subDay(),
            'recorded_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.observations', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('chartData.series.heart_rate', fn ($points) => count($points) === 2));
    }

    public function test_staff_can_record_clinical_observation(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-2',
            'slug' => 'pt-obs-2',
            'name' => 'Jane Patient',
        ]);

        $this->actingAs($user)
            ->from(route('patients.observations', $patient->url_key))
            ->post(route('patients.vitals.store', $patient->url_key), [
                'respiration_rate' => 16,
                'heart_rate' => 72,
                'bp_systolic' => 120,
                'spo2' => 98,
                'supplemental_oxygen' => false,
                'temperature_celsius' => 36.8,
                'consciousness_level' => 'alert',
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

    public function test_extended_observation_fields_can_be_saved(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-ext',
            'slug' => 'pt-obs-ext',
            'name' => 'Extended Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.vitals.store', $patient->url_key), [
                'respiration_rate' => 18,
                'heart_rate' => 88,
                'bp_systolic' => 118,
                'bp_diastolic' => 76,
                'spo2' => 96,
                'supplemental_oxygen' => false,
                'temperature_celsius' => 38.6,
                'consciousness_level' => 'alert',
                'blood_glucose_mmol' => 12.5,
                'weight_kg' => 72.4,
                'pain_score' => 8,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('patient_vitals', [
            'patient_id' => $patient->id,
            'bp_diastolic' => 76,
            'pain_score' => 8,
        ]);
    }

    public function test_critical_spo2_triggers_profile_alert(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-obs-alert',
            'slug' => 'pt-obs-alert',
            'name' => 'Alert Patient',
        ]);

        PatientVital::query()->create([
            'patient_id' => $patient->id,
            'heart_rate' => 80,
            'bp_systolic' => 120,
            'spo2' => 88,
            'recorded_at' => now(),
            'recorded_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.show', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientRecord')
                ->where('activeAlerts', fn ($alerts) => collect($alerts)->contains(fn ($message) => str_contains($message, 'Critical SpO₂'))));
    }

    public function test_observations_are_listed_newest_first(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
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
