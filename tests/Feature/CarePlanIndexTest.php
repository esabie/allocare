<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientCarePlanForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarePlanIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_plan_index_uses_catalogue_and_saved_status(): void
    {
        $user = User::factory()->create(['primary_role' => 'admin', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-care-plans',
            'slug' => 'pt-care-plans',
            'name' => 'Care Plan Patient',
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
                ->has('carePlans', 20)
                ->where('carePlans', fn ($plans) => collect($plans)->contains(
                    fn ($plan) => $plan['slug'] === 'wound-care'
                        && $plan['status'] === 'Active'
                        && in_array('Pressure risk', $plan['risks'], true)
                )
                    && collect($plans)->contains(
                        fn ($plan) => $plan['slug'] === 'personal-care-and-dignity'
                            && $plan['status'] === 'Not started'
                    )));
    }
}
