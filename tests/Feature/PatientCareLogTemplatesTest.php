<?php

namespace Tests\Feature;

use App\Models\CareJournalEntry;
use App\Models\Patient;
use App\Models\PatientCarePlanSummary;
use App\Models\PatientRiskAssessment;
use App\Models\User;
use App\Support\CareLogTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientCareLogTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_log_templates_page_includes_templates_and_link_options(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-care-logs',
            'slug' => 'pt-care-logs',
            'name' => 'Care Log Patient',
        ]);

        PatientCarePlanSummary::query()->create([
            'patient_slug' => $patient->url_key,
            'plan_slug' => 'personal-care-and-dignity',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        PatientRiskAssessment::query()->create([
            'patient_id' => $patient->id,
            'risk_slug' => 'falls',
            'risk_level' => 'amber',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('patients.notes', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientNotes')
                ->has('templates', 13)
                ->has('linkOptions.care_plans', 1)
                ->has('linkOptions.risk_assessments', 1));
    }

    public function test_staff_can_save_structured_personal_care_log(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-care-save',
            'slug' => 'pt-care-save',
            'name' => 'Save Log Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.notes.store', $patient->url_key), [
                'template_slug' => 'personal_care',
                'structured_data' => [
                    'activities' => 'Full wash and hair care',
                    'outcome' => 'assisted',
                ],
                'outcome_status' => 'completed',
                'linked_care_plan_slug' => 'personal-care-and-dignity',
                'linked_support_objective' => 'Maintain dignity with personal care',
                'linked_risk_assessment_slug' => 'falls',
            ])
            ->assertRedirect(route('patients.notes', $patient->url_key));

        $entry = CareJournalEntry::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame('personal_care', $entry->template_slug);
        $this->assertSame('completed', $entry->outcome_status);
        $this->assertStringContainsString('[Personal care]', $entry->body);
        $this->assertStringContainsString('Linked care plan:', $entry->body);
    }

    public function test_structured_log_requires_at_least_one_template_field(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-care-val',
            'slug' => 'pt-care-val',
            'name' => 'Validation Patient',
        ]);

        $this->actingAs($user)
            ->from(route('patients.notes', $patient->url_key))
            ->post(route('patients.notes.store', $patient->url_key), [
                'template_slug' => 'oral_care',
                'structured_data' => [],
            ])
            ->assertSessionHasErrors('structured_data');

        $this->assertDatabaseCount('care_journal_entries', 0);
    }

    public function test_care_log_body_builder_includes_links(): void
    {
        $body = CareLogTemplates::buildBody(
            'mobility',
            ['activity' => 'walk', 'assistance' => 'one_person'],
            'completed',
            'mobility-and-moving',
            'Improve safe indoor mobility',
            'falls',
        );

        $this->assertStringContainsString('[Mobility]', $body);
        $this->assertStringContainsString('Linked care plan: Mobility & Moving / Handling', $body);
        $this->assertStringContainsString('Support objective: Improve safe indoor mobility', $body);
        $this->assertStringContainsString('Linked risk assessment:', $body);
    }
}
