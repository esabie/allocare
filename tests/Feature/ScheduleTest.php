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

    private function patient(): Patient
    {
        return Patient::query()->create([
            'url_key' => 'pt-sched-1',
            'slug' => 'pt-sched-1',
            'name' => 'Schedule Patient',
        ]);
    }

    public function test_overnight_shift_is_stored_across_midnight(): void
    {
        $user = $this->careWorker();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $user->id,
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

    public function test_same_day_shift_still_works(): void
    {
        $user = $this->careWorker();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $user->id,
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
        $user = $this->careWorker();
        $patient = $this->patient();

        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => '2026-05-24 22:00:00',
            'end_at' => '2026-05-25 06:00:00',
        ]);

        $this->actingAs($user)
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
}
