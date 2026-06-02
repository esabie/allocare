<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientBowelRecord;
use App\Models\PatientFluidRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientFluidBowelTest extends TestCase
{
    use RefreshDatabase;

    public function test_observations_page_includes_fluid_and_bowel_data(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-fb-1',
            'slug' => 'pt-fb-1',
            'name' => 'Fluid Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.observations', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientObservations')
                ->has('fluidRecords')
                ->has('bowelRecords')
                ->has('bristolOptions'));
    }

    public function test_staff_can_record_fluid_balance(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-fb-fluid',
            'slug' => 'pt-fb-fluid',
            'name' => 'Hydration Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.fluid.store', $patient->url_key), [
                'fluid_intake_ml' => 250,
                'fluid_output_ml' => 100,
                'fluid_type' => 'oral',
            ])
            ->assertRedirect(route('patients.observations', $patient->url_key));

        $this->assertDatabaseHas('patient_fluid_records', [
            'patient_id' => $patient->id,
            'fluid_intake_ml' => 250,
            'fluid_output_ml' => 100,
        ]);
    }

    public function test_fluid_requires_intake_or_output(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-fb-val',
            'slug' => 'pt-fb-val',
            'name' => 'Val Patient',
        ]);

        $this->actingAs($user)
            ->from(route('patients.observations', $patient->url_key))
            ->post(route('patients.fluid.store', $patient->url_key), [])
            ->assertSessionHasErrors('fluid_intake_ml');

        $this->assertDatabaseCount('patient_fluid_records', 0);
    }

    public function test_staff_can_record_bowel_chart_entry(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-fb-bowel',
            'slug' => 'pt-fb-bowel',
            'name' => 'Bowel Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.bowel.store', $patient->url_key), [
                'bristol_type' => 4,
                'bowel_opened' => true,
                'continence_status' => 'Continent',
            ])
            ->assertRedirect(route('patients.observations', $patient->url_key));

        $this->assertDatabaseHas('patient_bowel_records', [
            'patient_id' => $patient->id,
            'bristol_type' => 4,
            'bowel_opened' => true,
        ]);
    }
}
