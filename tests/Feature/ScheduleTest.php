<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function careWorker(): User
    {
        return User::factory()->create([
            'primary_role' => 'care_worker',
        ]);
    }

    private function careManager(): User
    {
        return User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);
    }

    private function patient(array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'url_key' => 'pt-sched-1',
            'slug' => 'pt-sched-1',
            'name' => 'Schedule Patient',
            'lifecycle_status' => Patient::LIFECYCLE_ACTIVE,
        ], $overrides));
    }

    public function test_overnight_shift_is_stored_across_midnight(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $patient = $this->patient();

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-24',
                'start_time' => '22:00',
                'end_time' => '06:00',
                'purpose' => 'Night cover',
            ])
            ->assertRedirect(route('schedules'));

        $schedule = PatientSchedule::query()->first();
        $this->assertNotNull($schedule);
        $this->assertSame('2026-05-24 22:00:00', $schedule->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-25 06:00:00', $schedule->end_at->format('Y-m-d H:i:s'));
    }

    public function test_day_shift_can_be_booked_after_overnight_shift_ends(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $patient = $this->patient();

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $carer->id,
            'start_at' => '2026-05-24 22:00:00',
            'end_at' => '2026-05-25 06:00:00',
        ]);

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-25',
                'start_time' => '09:00',
                'end_time' => '17:00',
                'purpose' => 'Day cover',
            ])
            ->assertRedirect(route('schedules'));

        $this->assertSame(2, PatientSchedule::query()->count());
    }

    public function test_overlapping_shift_is_rejected(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $patient = $this->patient();

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $carer->id,
            'start_at' => '2026-05-24 09:00:00',
            'end_at' => '2026-05-24 17:00:00',
        ]);

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-24',
                'start_time' => '16:00',
                'end_time' => '20:00',
            ])
            ->assertSessionHasErrors('start_time');
    }

    public function test_inactive_patient_cannot_be_booked(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $patient = $this->patient(['lifecycle_status' => Patient::LIFECYCLE_INACTIVE]);

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-24',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ])
            ->assertSessionHasErrors('patient_url_key');
    }

    public function test_same_day_shift_still_works(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $patient = $this->patient();

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-24',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ])
            ->assertRedirect(route('schedules'));

        $schedule = PatientSchedule::query()->first();
        $this->assertSame('2026-05-24 09:00:00', $schedule->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-24 17:00:00', $schedule->end_at->format('Y-m-d H:i:s'));
    }

    public function test_overnight_shift_can_be_rescheduled(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $patient = $this->patient();

        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $carer->id,
            'start_at' => '2026-05-24 22:00:00',
            'end_at' => '2026-05-25 06:00:00',
        ]);

        $this->actingAs($manager)
            ->patch(route('schedules.reschedule', $schedule), [
                'patient_url_key' => $patient->url_key,
                'visit_date' => '2026-05-26',
                'start_time' => '21:00',
                'end_time' => '05:00',
            ])
            ->assertRedirect(route('schedules'));

        $schedule->refresh();
        $this->assertSame('2026-05-26 21:00:00', $schedule->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-27 05:00:00', $schedule->end_at->format('Y-m-d H:i:s'));
    }

    public function test_schedules_page_can_focus_a_single_patient(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $focused = $this->patient([
            'url_key' => 'pt-focus',
            'slug' => 'pt-focus',
            'name' => 'Focused Patient',
        ]);
        $other = $this->patient([
            'url_key' => 'pt-other',
            'slug' => 'pt-other',
            'name' => 'Other Patient',
        ]);

        PatientSchedule::query()->create([
            'patient_id' => $focused->id,
            'assigned_user_id' => $carer->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'purpose' => 'Focused visit',
        ]);
        PatientSchedule::query()->create([
            'patient_id' => $other->id,
            'assigned_user_id' => $carer->id,
            'start_at' => now()->addDay()->setTime(12, 0),
            'end_at' => now()->addDay()->setTime(14, 0),
            'purpose' => 'Other visit',
        ]);

        $this->actingAs($manager)
            ->get(route('patients.schedules', ['patient' => $focused->url_key, 'book' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Schedules')
                ->where('focusPatient.urlKey', $focused->url_key)
                ->where('openBooking', true)
                ->has('patients', 1)
                ->where('patients.0.urlKey', $focused->url_key)
                ->has('entries', 1)
                ->where('entries.0.patientUrlKey', $focused->url_key));
    }

    public function test_store_keeps_patient_focus_when_requested(): void
    {
        $manager = $this->careManager();
        $carer = $this->careWorker();
        $patient = $this->patient([
            'url_key' => 'pt-keep-focus',
            'slug' => 'pt-keep-focus',
            'name' => 'Keep Focus Patient',
        ]);

        $this->actingAs($manager)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $carer->id,
                'visit_date' => '2026-05-24',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'purpose' => 'Focused booking',
                'keep_patient_focus' => 1,
            ])
            ->assertRedirect(route('patients.schedules', ['patient' => $patient->url_key]));
    }
}
