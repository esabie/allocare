<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientContactsTest extends TestCase
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
            'gp_name' => 'Dr Original',
            'next_of_kin' => 'Jane Relative',
        ], $overrides));
    }

    public function test_contacts_page_exposes_social_services_fields(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient([
            'url_key' => 'ac-contacts-1',
            'social_worker_name' => 'Linda Murray',
            'social_worker_contact' => '020 7946 0100',
            'social_services_number' => 'SS-445566',
        ]);

        $this->actingAs($manager)
            ->get(route('patients.contacts', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PatientContacts')
                ->where('canEditContacts', true)
                ->where('contactValues.social_worker_name', 'Linda Murray')
                ->where('contactValues.social_worker_contact', '020 7946 0100')
                ->where('contactValues.social_services_number', 'SS-445566')
                ->has('patientContactData.professional', 3)
                ->where('patientContactData.professional.0.key', 'social_services')
                ->where('patientContactData.professional.0.lines.0.value', 'Linda Murray')
            );
    }

    public function test_care_manager_can_update_social_services_without_wiping_other_profile_fields(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient([
            'url_key' => 'ac-contacts-2',
            'gp_name' => 'Dr Original',
            'next_of_kin' => 'Jane Relative',
            'social_worker_name' => 'Old Worker',
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.profile.update', $patient->url_key), [
                'social_worker_name' => 'New Social Worker',
                'social_worker_contact' => 'social.worker@authority.gov.uk',
                'social_services_number' => 'SS-778899',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $patient->refresh();
        $this->assertSame('New Social Worker', $patient->social_worker_name);
        $this->assertSame('social.worker@authority.gov.uk', $patient->social_worker_contact);
        $this->assertSame('SS-778899', $patient->social_services_number);
        $this->assertSame('Dr Original', $patient->gp_name);
        $this->assertSame('Jane Relative', $patient->next_of_kin);
    }

    public function test_social_services_update_is_audit_logged_with_before_and_after(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient([
            'url_key' => 'ac-contacts-3',
            'social_worker_name' => 'Before Worker',
            'social_worker_contact' => '07000000000',
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.profile.update', $patient->url_key), [
                'social_worker_name' => 'After Worker',
                'social_worker_contact' => '07000000001',
            ])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()
            ->where('subject_type', 'patient')
            ->where('subject_key', $patient->url_key)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($manager->id, $event->user_id);
        $this->assertStringContainsString('Updated contact details', $event->description);
        $this->assertSame('Before Worker', $event->previous_values['social_worker_name'] ?? null);
        $this->assertSame('After Worker', $event->new_values['social_worker_name'] ?? null);
        $this->assertSame('07000000000', $event->previous_values['social_worker_contact'] ?? null);
        $this->assertSame('07000000001', $event->new_values['social_worker_contact'] ?? null);
        $this->assertNotNull($event->created_at);
    }

    public function test_care_worker_cannot_update_contact_details(): void
    {
        $carer = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = $this->createPatient(['url_key' => 'ac-contacts-4']);

        $this->actingAs($carer)
            ->patch(route('patients.profile.update', $patient->url_key), [
                'social_worker_name' => 'Should Not Save',
            ])
            ->assertForbidden();
    }
}
