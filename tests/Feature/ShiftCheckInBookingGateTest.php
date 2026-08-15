<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCheckInBookingGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_page_redirects_when_no_booking_exists(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-checkin-gate-1',
            'slug' => 'pt-checkin-gate-1',
            'name' => 'No Booking Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.shift-checkin', $patient->url_key))
            ->assertRedirect(route('patients.show', $patient->url_key));
    }

    public function test_check_in_page_allows_eligible_booking(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-checkin-gate-2',
            'slug' => 'pt-checkin-gate-2',
            'name' => 'Booked Patient',
        ]);
        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->subMinutes(15),
            'end_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->get(route('patients.shift-checkin', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ShiftCheckIn')
                ->where('patientContext.activeScheduleId', fn ($id) => $id !== null));
    }

    public function test_patient_profile_exposes_can_check_in_flag(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-checkin-gate-3',
            'slug' => 'pt-checkin-gate-3',
            'name' => 'Profile Gate Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.show', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientRecord')
                ->where('canCheckIn', false));

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->addMinutes(30),
            'end_at' => now()->addMinutes(90),
        ]);

        $this->actingAs($user)
            ->get(route('patients.show', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PatientRecord')
                ->where('canCheckIn', true));
    }

    public function test_session_start_rejects_when_no_eligible_booking(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-checkin-gate-4',
            'slug' => 'pt-checkin-gate-4',
            'name' => 'Session Gate Patient',
        ]);

        $this->actingAs($user)
            ->postJson(route('patients.shift-checkin.session.start', $patient->url_key), [])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_far_future_booking_is_not_eligible_yet(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-checkin-gate-5',
            'slug' => 'pt-checkin-gate-5',
            'name' => 'Future Booking Patient',
        ]);
        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->addHours(3),
            'end_at' => now()->addHours(4),
        ]);

        $this->actingAs($user)
            ->get(route('patients.shift-checkin', $patient->url_key))
            ->assertRedirect(route('patients.show', $patient->url_key));

        $this->actingAs($user)
            ->get(route('patients.show', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canCheckIn', false));
    }
}
