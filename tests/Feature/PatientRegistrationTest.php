<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function minimalRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Mrs.',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'date_of_birth' => '1975-06-15',
            'care_group' => 'community_care',
            'address_line_1' => '10 High Street',
            'city' => 'Manchester',
            'postcode' => 'M1 1AA',
            'next_of_kin' => 'John Smith',
            'next_of_kin_tel' => '07444555666',
            'start_date' => '2026-01-01',
            'name' => 'Mrs. Jane Smith',
            'dob' => '15/06/1975',
            'address' => '10 High Street, Manchester, M1 1AA',
            'status' => 'GREEN',
        ], $overrides);
    }

    public function test_minimal_emergency_registration_succeeds_without_optional_fields(): void
    {
        Carbon::setTestNow('2026-06-21 10:00:00');

        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('patients.store'), $this->minimalRegistrationPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $patient = Patient::query()->where('name', 'Mrs. Jane Smith')->first();
        $this->assertNotNull($patient);
        $this->assertNull($patient->nhs_number);
        $this->assertNull($patient->email);
        $this->assertNull($patient->weight_kg);
        $this->assertNull($patient->height_m);
        $this->assertNull($patient->photo_path);
        $this->assertTrue(\App\Support\PatientRegistration::isProfileIncomplete($patient));
        $this->assertSame('community_care', $patient->care_group);
        $this->assertSame('2026-01-01', $patient->service_start_date->format('Y-m-d'));
        $this->assertNotNull($patient->profile_completion_due_at);
        $this->assertTrue($patient->profile_completion_due_at->equalTo(now()->addHours(72)));
    }

    public function test_registration_requires_emergency_contact_phone(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('patients.store'), $this->minimalRegistrationPayload([
                'next_of_kin_tel' => '',
            ]))
            ->assertSessionHasErrors(['next_of_kin_tel']);
    }

    public function test_registration_requires_care_group_and_start_date(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('patients.store'), $this->minimalRegistrationPayload([
                'care_group' => '',
                'start_date' => '',
            ]))
            ->assertSessionHasErrors(['care_group', 'start_date']);
    }

    public function test_patient_can_be_registered_with_full_optional_details(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $nhs = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);

        $this->actingAs($admin)
            ->post(route('patients.store'), $this->minimalRegistrationPayload([
                'gender' => 'Female',
                'rag_status' => 'green',
                'staffing_ratio' => '1:1 Support',
                'phone_number' => '07111222333',
                'email_address' => 'jane.smith.'.uniqid().'@example.com',
                'next_of_kin_email' => 'john.'.uniqid().'@example.com',
                'social_services_number' => 'SS-99999',
                'weight_kg' => 65,
                'height_m' => 1.68,
                'primary_diagnosis' => 'Stroke recovery',
                'severe_allergies' => 'Penicillin',
                'gp_name' => 'Dr Patel',
                'gp_practice' => 'Oak Lane Medical Centre',
                'capacity_status' => 'Has capacity',
                'primary_language' => 'English',
                'nhs_number' => $nhs,
                'phone' => '07111222333',
                'allergies' => 'Penicillin',
                'photo' => \Illuminate\Http\UploadedFile::fake()->image('patient.jpg'),
            ]))
            ->assertSessionHasNoErrors();

        $patient = Patient::query()->where('nhs_number', $nhs)->first();
        $this->assertNotNull($patient);
        $this->assertSame('Dr Patel', $patient->gp_name);
        $this->assertSame(65.0, (float) $patient->weight_kg);
        $this->assertSame(1.68, (float) $patient->height_m);
        $this->assertNotNull($patient->photo_path);
    }

    public function test_patient_can_be_registered_without_photo(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('patients.store'), $this->minimalRegistrationPayload([
                'title' => 'Mr.',
                'first_name' => 'No',
                'last_name' => 'Photo',
                'name' => 'Mr. No Photo',
                'date_of_birth' => '1980-01-01',
                'dob' => '01/01/1980',
            ]))
            ->assertSessionHasNoErrors();

        $patient = Patient::query()->where('name', 'Mr. No Photo')->first();
        $this->assertNotNull($patient);
        $this->assertNull($patient->photo_path);
    }

    public function test_nhs_number_must_be_ten_digits_when_provided(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('patients.store'), $this->minimalRegistrationPayload([
                'nhs_number' => '12345',
            ]))
            ->assertSessionHasErrors(['nhs_number']);
    }

    public function test_overdue_incomplete_profile_appears_on_care_alerts(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');

        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        Patient::query()->create([
            'url_key' => 'pt-incomplete',
            'slug' => 'pt-incomplete',
            'name' => 'Incomplete Profile Patient',
            'lifecycle_status' => Patient::LIFECYCLE_ACTIVE,
            'profile_completion_due_at' => now()->subHour(),
            'profile_completed_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('care-alerts'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('alerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($alert) => ($alert['label'] ?? '') === 'INCOMPLETE PROFILE OVERDUE'
                        && ($alert['patientUrlKey'] ?? '') === 'pt-incomplete'
                )));
    }
}
