<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientCarePlanForm;
use App\Models\PatientCarePlanModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarePlanIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_plan_index_shows_only_assigned_modules(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-care-plans',
            'slug' => 'pt-care-plans',
            'name' => 'Care Plan Patient',
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'wound-care',
            'sort_order' => 0,
            'activated_at' => now(),
            'activated_by_user_id' => $user->id,
        ]);

        PatientCarePlanForm::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'wound-care',
            'status' => 'submitted',
            'data' => ['linked_risks_rag' => 'Pressure risk, Infection'],
            'schema_version' => 2,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.careplans', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('carePlans', 1)
                ->has('moduleCatalogue')
                ->where('canConfigureModules', true)
                ->where('carePlans', fn ($plans) => collect($plans)->contains(
                    fn ($plan) => $plan['slug'] === 'wound-care'
                        && $plan['status'] === 'Active'
                        && in_array('Pressure risk', $plan['risks'], true)
                )));
    }

    public function test_legacy_saved_plans_are_synced_into_module_assignments(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-legacy-sync',
            'slug' => 'pt-legacy-sync',
            'name' => 'Legacy Patient',
        ]);

        PatientCarePlanForm::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'personal-care-and-dignity',
            'status' => 'draft',
            'data' => ['linked_risks_rag' => 'None'],
            'schema_version' => 2,
            'updated_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('patients.careplans', $patient->url_key))
            ->assertOk();

        $this->assertDatabaseHas('patient_care_plan_modules', [
            'patient_id' => $patient->id,
            'module_slug' => 'personal-care-and-dignity',
        ]);
    }
}
