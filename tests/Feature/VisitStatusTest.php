<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientSchedule;
use App\Models\User;
use App\Support\VisitStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_visit_without_check_in_is_missed(): void
    {
        $now = Carbon::parse('2026-06-23 06:30:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-missed',
            'slug' => 'pt-missed',
            'name' => 'Missed Patient',
        ]);

        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => $now->copy()->subHours(7),
            'end_at' => $now->copy()->subMinutes(30),
            'purpose' => 'Monitoring',
        ]);

        $this->assertSame(VisitStatus::MISSED, VisitStatus::classify($schedule, $now));
    }

    public function test_in_window_visit_without_check_in_is_upcoming_not_in_progress(): void
    {
        $now = Carbon::parse('2026-06-23 01:00:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-due',
            'slug' => 'pt-due',
            'name' => 'Due Now Patient',
        ]);

        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => $now->copy()->subHours(3),
            'end_at' => $now->copy()->addHours(5),
            'purpose' => 'Night monitoring',
        ]);

        $this->assertSame(VisitStatus::UPCOMING, VisitStatus::classify($schedule, $now));
    }

    public function test_checked_in_visit_is_in_progress(): void
    {
        $now = Carbon::parse('2026-06-23 01:00:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create();
        $patient = Patient::query()->create([
            'url_key' => 'pt-active',
            'slug' => 'pt-active',
            'name' => 'Active Patient',
        ]);

        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => $now->copy()->subHour(),
            'end_at' => $now->copy()->addHours(5),
            'checked_in_at' => $now->copy()->subMinutes(20),
            'purpose' => 'Night monitoring',
        ]);

        $this->assertSame(VisitStatus::IN_PROGRESS, VisitStatus::classify($schedule, $now));
    }

    public function test_dashboard_visit_metrics_sum_to_total(): void
    {
        $now = Carbon::parse('2026-06-23 01:00:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email_verified_at' => $now]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-dash',
            'slug' => 'pt-dash',
            'name' => 'Dashboard Patient',
        ]);

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => $now->copy()->startOfWeek(Carbon::SUNDAY)->addHours(22),
            'end_at' => $now->copy()->startOfWeek(Carbon::SUNDAY)->addDay()->setTime(6, 0),
            'purpose' => 'Due now shift',
        ]);

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => $now->copy()->setTime(12, 0),
            'end_at' => $now->copy()->setTime(18, 0),
            'purpose' => 'Later shift',
        ]);

        PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => $now->copy()->subDay()->setTime(23, 0),
            'end_at' => $now->copy()->setTime(5, 0),
            'purpose' => 'Overnight ended',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardStats.visits.total', 3)
                ->where('dashboardStats.visits.metrics.complete', 0)
                ->where('dashboardStats.visits.metrics.inProgress', 0)
                ->where('dashboardStats.visits.metrics.upcoming', 2)
                ->where('dashboardStats.visits.metrics.missed', 1));
    }
}
