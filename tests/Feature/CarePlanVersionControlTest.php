<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientCarePlanForm;
use App\Models\PatientCarePlanModule;
use App\Models\PatientCarePlanVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarePlanVersionControlTest extends TestCase
{
    use RefreshDatabase;

    private function safeguardingPayload(): array
    {
        return [
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
    }

    public function test_save_creates_version_with_author_and_timestamp(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-version',
            'slug' => 'pt-version',
            'name' => 'Version Patient',
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'safeguarding',
            'sort_order' => 0,
            'activated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('patients.careplans.save', [
                'patient' => $patient->url_key,
                'plan' => 'safeguarding',
            ]), [
                'data' => $this->safeguardingPayload(),
            ])
            ->assertRedirect();

        $version = PatientCarePlanVersion::query()->first();
        $this->assertNotNull($version);
        $this->assertSame(1, $version->version_number);
        $this->assertSame($user->id, $version->recorded_by_user_id);
        $this->assertNotNull($version->recorded_at);
        $this->assertSame('safeguarding', $version->plan_slug);
    }

    public function test_subsequent_save_retains_previous_version(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-version-2',
            'slug' => 'pt-version-2',
            'name' => 'Version Patient Two',
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'safeguarding',
            'sort_order' => 0,
            'activated_at' => now(),
        ]);

        $payload = $this->safeguardingPayload();

        $this->actingAs($user)->post(route('patients.careplans.save', [
            'patient' => $patient->url_key,
            'plan' => 'safeguarding',
        ]), ['data' => $payload]);

        $payload['linked_risks_rag'] = 'Updated safeguarding concern';
        $this->actingAs($user)->post(route('patients.careplans.save', [
            'patient' => $patient->url_key,
            'plan' => 'safeguarding',
        ]), ['data' => $payload]);

        $this->assertSame(2, PatientCarePlanVersion::query()->count());
        $this->assertDatabaseHas('patient_care_plan_versions', [
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
            'version_number' => 1,
        ]);
        $this->assertDatabaseHas('patient_care_plan_versions', [
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
            'version_number' => 2,
        ]);
    }

    public function test_care_worker_cannot_save_care_plan(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-readonly',
            'slug' => 'pt-readonly',
            'name' => 'Readonly Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.careplans.save', [
                'patient' => $patient->url_key,
                'plan' => 'safeguarding',
            ]), [
                'data' => $this->safeguardingPayload(),
            ])
            ->assertForbidden();
    }

    public function test_supervisor_cannot_save_care_plan(): void
    {
        $user = User::factory()->create(['primary_role' => 'supervisor', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-supervisor',
            'slug' => 'pt-supervisor',
            'name' => 'Supervisor Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.careplans.save', [
                'patient' => $patient->url_key,
                'plan' => 'safeguarding',
            ]), [
                'data' => $this->safeguardingPayload(),
            ])
            ->assertForbidden();
    }

    public function test_care_plan_detail_includes_version_history(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-history',
            'slug' => 'pt-history',
            'name' => 'History Patient',
        ]);

        PatientCarePlanModule::query()->create([
            'patient_id' => $patient->id,
            'module_slug' => 'safeguarding',
            'sort_order' => 0,
            'activated_at' => now(),
        ]);

        PatientCarePlanForm::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
            'status' => 'submitted',
            'data' => $this->safeguardingPayload(),
            'schema_version' => 2,
            'submitted_at' => now(),
            'updated_by_user_id' => $user->id,
        ]);

        PatientCarePlanVersion::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'safeguarding',
            'version_number' => 1,
            'data' => $this->safeguardingPayload(),
            'schema_version' => 2,
            'status' => 'submitted',
            'review_due_at' => now()->addMonth()->toDateString(),
            'change_summary' => 'Initial version',
            'recorded_by_user_id' => $user->id,
            'recorded_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('patients.careplans.show', ['patient' => $patient->url_key, 'plan' => 'safeguarding']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('versions', 1)
                ->has('auditMeta')
                ->where('canEditCarePlan', true));
    }
}
