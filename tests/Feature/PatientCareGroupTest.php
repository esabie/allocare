<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Patient;
use App\Models\PatientCareGroupVersion;
use App\Models\PatientSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientCareGroupTest extends TestCase
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
            'care_group' => 'community_care',
        ], $overrides));
    }

    public function test_care_manager_can_update_patient_care_group_with_version_history(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient([
            'url_key' => 'ac-cg-1',
            'care_group' => 'community_care',
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.care-group', $patient->url_key), [
                'care_group' => 'complex_care',
                'reason' => 'Increased clinical needs',
            ])
            ->assertRedirect();

        $patient->refresh();
        $this->assertSame('complex_care', $patient->care_group);

        $this->assertDatabaseHas('patient_care_group_versions', [
            'patient_id' => $patient->id,
            'previous_care_group' => 'community_care',
            'new_care_group' => 'complex_care',
            'changed_by_user_id' => $manager->id,
            'reason' => 'Increased clinical needs',
        ]);
    }

    public function test_care_group_update_is_audit_logged(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient([
            'url_key' => 'ac-cg-2',
            'care_group' => 'community_care',
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.care-group', $patient->url_key), [
                'care_group' => 'palliative_care',
            ])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()
            ->where('subject_type', 'patient')
            ->where('subject_key', $patient->url_key)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString('Care group updated', $event->description);
        $this->assertSame('community_care', $event->previous_values['care_group'] ?? null);
        $this->assertSame('palliative_care', $event->new_values['care_group'] ?? null);
    }

    public function test_patient_profile_includes_care_group_history(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-cg-3', 'care_group' => 'community_care']);

        PatientCareGroupVersion::query()->create([
            'patient_id' => $patient->id,
            'previous_care_group' => null,
            'new_care_group' => 'community_care',
            'changed_by_user_id' => $manager->id,
            'reason' => 'Initial registration',
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($manager)
            ->get(route('patients.show', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PatientRecord')
                ->has('careGroups', 7)
                ->has('careGroupHistory', 1)
                ->where('careGroupHistory.0.newCareGroup', 'community_care')
            );
    }

    public function test_care_worker_cannot_update_patient_care_group(): void
    {
        $worker = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-cg-4']);

        $this->actingAs($worker)
            ->patch(route('patients.care-group', $patient->url_key), [
                'care_group' => 'complex_care',
            ])
            ->assertForbidden();
    }

    public function test_registration_records_initial_care_group_version(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $payload = [
            'title' => 'Mr.',
            'first_name' => 'Care',
            'last_name' => 'Group',
            'date_of_birth' => '1980-01-01',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'start_date' => now()->toDateString(),
            'care_group' => 'acute_response',
            'next_of_kin' => 'Jane Group',
            'next_of_kin_tel' => '07700900000',
            'name' => 'Mr. Care Group',
            'dob' => '01/01/1980',
            'address' => '1 Test Street, London, SW1A 1AA',
            'status' => 'GREEN',
            'rag_status' => 'green',
        ];

        $this->actingAs($manager)
            ->post(route('patients.store'), $payload)
            ->assertRedirect();

        $patient = Patient::query()->where('care_group', 'acute_response')->first();
        $this->assertNotNull($patient);

        $this->assertDatabaseHas('patient_care_group_versions', [
            'patient_id' => $patient->id,
            'previous_care_group' => null,
            'new_care_group' => 'acute_response',
            'reason' => 'Initial registration',
        ]);
    }

    public function test_schedule_rejects_staff_not_assigned_to_patient_care_group(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'assigned_care_groups' => ['community_care'],
        ]);

        $patient = $this->patientWithCareGroup('complex_care');

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-24',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ])
            ->assertSessionHasErrors(['assigned_user_id']);
    }

    public function test_schedule_allows_staff_assigned_to_patient_care_group(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'assigned_care_groups' => ['complex_care', 'community_care'],
        ]);

        $patient = $this->patientWithCareGroup('complex_care');

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-24',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ])
            ->assertRedirect(route('schedules'));

        $this->assertSame(1, PatientSchedule::query()->count());
    }

    private function patientWithCareGroup(string $careGroup): Patient
    {
        return Patient::query()->create([
            'url_key' => 'pt-cg-'.random_int(1000, 9999),
            'slug' => 'pt-cg',
            'name' => 'Care Group Patient',
            'lifecycle_status' => Patient::LIFECYCLE_ACTIVE,
            'care_group' => $careGroup,
        ]);
    }
}
