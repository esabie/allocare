<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientCarePlanModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicCarePlanBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_care_plan_modules(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-modules',
            'slug' => 'pt-modules',
            'name' => 'Module Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.careplans.modules.store', $patient->url_key), [
                'module_slugs' => ['mobility-and-moving', 'nutrition-and-hydration'],
            ])
            ->assertRedirect(route('patients.careplans', $patient->url_key));

        $this->assertDatabaseHas('patient_care_plan_modules', [
            'patient_id' => $patient->id,
            'module_slug' => 'mobility-and-moving',
        ]);
        $this->assertDatabaseHas('patient_care_plan_modules', [
            'patient_id' => $patient->id,
            'module_slug' => 'nutrition-and-hydration',
        ]);
    }

    public function test_manager_can_create_bespoke_section(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-bespoke',
            'slug' => 'pt-bespoke',
            'name' => 'Bespoke Patient',
        ]);

        $response = $this->actingAs($user)
            ->post(route('patients.careplans.modules.bespoke', $patient->url_key), [
                'title' => 'Sensory Integration Support',
                'purpose' => 'Supports for sensory processing and regulation.',
            ]);

        $assignment = PatientCarePlanModule::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->is_bespoke);
        $this->assertSame('Sensory Integration Support', $assignment->custom_title);

        $response->assertRedirect(route('patients.careplans.show', [
            'patient' => $patient->url_key,
            'plan' => $assignment->module_slug,
        ]));
    }

    public function test_manager_can_remove_all_modules_without_legacy_resync(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-remove-all',
            'slug' => 'pt-remove-all',
            'name' => 'Remove All Patient',
            'care_plan_modules_initialized' => true,
        ]);

        foreach (['personal-care-and-dignity', 'mobility-and-moving', 'medication-support'] as $index => $slug) {
            PatientCarePlanModule::query()->create([
                'patient_id' => $patient->id,
                'module_slug' => $slug,
                'sort_order' => $index,
                'activated_at' => now(),
            ]);
        }

        \App\Models\PatientCarePlanForm::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'personal-care-and-dignity',
            'data' => ['review_date' => now()->addMonth()->toDateString()],
            'schema_version' => 2,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        foreach (['personal-care-and-dignity', 'mobility-and-moving', 'medication-support'] as $slug) {
            $this->actingAs($user)
                ->delete(route('patients.careplans.modules.destroy', [
                    'patient' => $patient->url_key,
                    'slug' => $slug,
                ]))
                ->assertRedirect(route('patients.careplans', $patient->url_key));
        }

        $this->assertDatabaseCount('patient_care_plan_modules', 0);

        $this->actingAs($user)
            ->get(route('patients.careplans', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientCarePlans')
                ->where('carePlans', []));
    }

    public function test_care_worker_cannot_configure_modules(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-no-config',
            'slug' => 'pt-no-config',
            'name' => 'Restricted Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.careplans.modules.store', $patient->url_key), [
                'module_slugs' => ['wound-care'],
            ])
            ->assertForbidden();
    }

    public function test_care_manager_can_save_care_plan(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-save',
            'slug' => 'pt-save',
            'name' => 'Save Patient',
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'safeguarding',
            'sort_order' => 0,
            'activated_at' => now(),
        ]);

        $payload = [
            'what_matters_to_me' => 'Dignity and safety at home.',
            'baseline_clinical_summary' => 'Requires safeguarding oversight.',
            'linked_risks_rag' => 'Safeguarding concern',
            'smart_outcomes' => 'Remain safe in the community.',
            'proactive_support' => 'Structured visits and observation.',
            'active_steps' => 'Follow safeguarding plan.',
            'reactive_steps' => 'Contact safeguarding lead.',
            'equipment_required' => 'None',
            'staff_competencies_training_required' => 'Safeguarding level 2',
            'monitoring_and_recording' => 'Daily notes',
            'escalation_pathway' => 'On-call manager then MASH',
            'capacity_consent_note' => 'Best interest process documented.',
            'review_due' => now()->addMonth()->toDateString(),
            'owner' => 'Care Manager',
            'primary_focus_0' => 'Previous MARAC involvement',
            'primary_focus_1' => 'Current risks',
            'primary_focus_2' => 'Agency contacts',
            'primary_focus_3' => 'Recording requirements',
            'primary_focus_4' => 'Review schedule',
        ];

        $this->actingAs($user)
            ->post(route('patients.careplans.save', [
                'patient' => $patient->url_key,
                'plan' => 'safeguarding',
            ]), [
                'data' => $payload,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('patient_care_plan_forms', [
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
        ]);
    }
}
