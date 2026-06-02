<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientHandover;
use App\Models\PatientSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientHandoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_handovers_page(): void
    {
        $this->get(route('patients.handovers', 'pt-ho-1'))->assertRedirect(route('login'));
    }

    public function test_staff_can_view_handovers_page(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-1',
            'slug' => 'pt-ho-1',
            'name' => 'Handover Patient',
        ]);

        $this->actingAs($user)
            ->get(route('patients.handovers', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('PatientHandovers'));
    }

    public function test_staff_can_save_day_handover(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-day',
            'slug' => 'pt-ho-day',
            'name' => 'Day Patient',
        ]);

        $this->actingAs($user)
            ->post(route('patients.handovers.store', $patient->url_key), [
                'shift_type' => 'day',
                'shift_date' => now()->toDateString(),
                'presentation' => 'Alert and well.',
                'care_delivered' => 'Personal care and repositioning completed.',
                'handover_notes' => 'No concerns for night team.',
            ])
            ->assertRedirect(route('patients.handovers', $patient->url_key));

        $this->assertDatabaseHas('patient_handovers', [
            'patient_id' => $patient->id,
            'shift_type' => 'day',
            'presentation' => 'Alert and well.',
            'author_user_id' => $user->id,
        ]);
    }

    public function test_staff_can_save_night_handover_linked_to_visit(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-night',
            'slug' => 'pt-ho-night',
            'name' => 'Night Patient',
        ]);
        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->subHours(8),
            'end_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('patients.handovers.store', $patient->url_key), [
                'shift_type' => 'night',
                'shift_date' => now()->toDateString(),
                'schedule_id' => $schedule->id,
                'sleep_summary' => 'Slept 6 hours with one brief wake.',
                'morning_priorities' => 'Assist with breakfast and morning meds.',
            ])
            ->assertRedirect(route('patients.handovers', $patient->url_key));

        $handover = PatientHandover::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($handover);
        $this->assertSame('night', $handover->shift_type);
        $this->assertSame($schedule->id, $handover->patient_schedule_id);
        $this->assertNull($handover->presentation);
    }

    public function test_day_handover_requires_at_least_one_field(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-ho-val',
            'slug' => 'pt-ho-val',
            'name' => 'Validation Patient',
        ]);

        $this->actingAs($user)
            ->from(route('patients.handovers', $patient->url_key))
            ->post(route('patients.handovers.store', $patient->url_key), [
                'shift_type' => 'day',
                'shift_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('presentation');

        $this->assertDatabaseCount('patient_handovers', 0);
    }
}
