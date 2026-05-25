<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_be_registered_without_diagnosis_or_allergies(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('patients.store'), [
                'title' => 'Mrs.',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'date_of_birth' => '1975-06-15',
                'gender' => 'Female',
                'rag_status' => 'green',
                'staffing_ratio' => '1:1 Support',
                'address_line_1' => '10 High Street',
                'city' => 'Manchester',
                'postcode' => 'M1 1AA',
                'phone_number' => '07111222333',
                'email_address' => 'jane.smith.'.uniqid().'@example.com',
                'next_of_kin' => 'John Smith',
                'next_of_kin_tel' => '07444555666',
                'next_of_kin_email' => 'john.'.uniqid().'@example.com',
                'social_services_number' => 'SS-99999',
                'weight_kg' => 65,
                'height_m' => 1.68,
                'start_date' => '2026-01-01',
                'name' => 'Mrs. Jane Smith',
                'nhs_number' => str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
                'dob' => '15/06/1975',
                'address' => '10 High Street, Manchester, M1 1AA',
                'phone' => '07111222333',
                'status' => 'ACTIVE',
                'photo' => \Illuminate\Http\UploadedFile::fake()->image('patient.jpg'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('patients'));

        $patient = Patient::query()->where('name', 'Mrs. Jane Smith')->first();
        $this->assertNotNull($patient);
        $this->assertSame(['None'], $patient->allergies);
    }

    public function test_patient_can_be_registered_without_photo(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('patients.store'), [
                'title' => 'Mr.',
                'first_name' => 'No',
                'last_name' => 'Photo',
                'date_of_birth' => '1980-01-01',
                'gender' => 'Male',
                'rag_status' => 'green',
                'staffing_ratio' => '1:1 Support',
                'address_line_1' => '1 Test Street',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'phone_number' => '07123456780',
                'email_address' => 'nophoto.'.uniqid().'@example.com',
                'next_of_kin' => 'Kin Person',
                'next_of_kin_tel' => '07987654320',
                'next_of_kin_email' => 'kin2.'.uniqid().'@example.com',
                'social_services_number' => 'SS-11111',
                'weight_kg' => 70,
                'height_m' => 1.75,
                'start_date' => '2026-01-01',
                'name' => 'Mr. No Photo',
                'nhs_number' => str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
                'dob' => '01/01/1980',
                'address' => '1 Test Street, London, SW1A 1AA',
                'phone' => '07123456780',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('patients'));

        $patient = Patient::query()->where('name', 'Mr. No Photo')->first();
        $this->assertNotNull($patient);
        $this->assertNull($patient->photo_path);
    }
}
