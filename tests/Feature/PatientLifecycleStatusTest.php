<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientLifecycleStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_manager_can_update_lifecycle_status(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $patient = Patient::query()->create([
            'url_key' => 'pt-life-1',
            'slug' => 'pt-life-1',
            'name' => 'Lifecycle Patient',
            'lifecycle_status' => Patient::LIFECYCLE_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->patch(route('patients.lifecycle-status', $patient->url_key), [
                'lifecycle_status' => Patient::LIFECYCLE_INACTIVE,
            ])
            ->assertRedirect();

        $patient->refresh();
        $this->assertSame(Patient::LIFECYCLE_INACTIVE, $patient->normalizedLifecycleStatus());
        $this->assertFalse($patient->isRosterable());
    }

    public function test_care_worker_cannot_update_lifecycle_status(): void
    {
        $worker = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $patient = Patient::query()->create([
            'url_key' => 'pt-life-2',
            'slug' => 'pt-life-2',
            'name' => 'Lifecycle Patient Two',
            'lifecycle_status' => Patient::LIFECYCLE_ACTIVE,
        ]);

        $this->actingAs($worker)
            ->patch(route('patients.lifecycle-status', $patient->url_key), [
                'lifecycle_status' => Patient::LIFECYCLE_FINISHED,
            ])
            ->assertForbidden();
    }

    public function test_new_patient_defaults_to_active_lifecycle_status(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $payload = [
            'title' => 'Mr.',
            'first_name' => 'Louis',
            'last_name' => 'Osei',
            'date_of_birth' => '1980-01-01',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'start_date' => now()->toDateString(),
            'care_group' => 'community_care',
            'next_of_kin' => 'Jane Osei',
            'next_of_kin_tel' => '07700900000',
            'name' => 'Mr. Louis Osei',
            'dob' => '01/01/1980',
            'address' => '1 Test Street, London, SW1A 1AA',
            'status' => 'GREEN',
            'rag_status' => 'green',
        ];

        $this->actingAs($manager)
            ->post(route('patients.store'), $payload)
            ->assertRedirect();

        $patient = Patient::query()->latest('id')->first();
        $this->assertNotNull($patient);
        $this->assertSame(Patient::LIFECYCLE_ACTIVE, $patient->normalizedLifecycleStatus());
    }
}
