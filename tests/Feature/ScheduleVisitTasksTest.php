<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientSchedule;
use App\Models\ScheduleVisitTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleVisitTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_visit_seeds_task_checklist(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_manager', 'email_verified_at' => now()]);
        $carer = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-tasks-1',
            'slug' => 'pt-tasks-1',
            'name' => 'Task Patient',
        ]);

        $this->actingAs($user)->post(route('schedules.store'), [
            'patient_url_key' => $patient->url_key,
            'assigned_user_id' => $carer->id,
            'visit_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertRedirect(route('schedules'));

        $schedule = PatientSchedule::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($schedule);
        $this->assertSame(count(visit_task_catalogue()), $schedule->visitTasks()->count());
    }

    public function test_carer_can_record_visit_task_outcomes(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-tasks-2',
            'slug' => 'pt-tasks-2',
            'name' => 'Visit Patient',
        ]);
        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        seed_schedule_visit_tasks($schedule);
        $task = $schedule->visitTasks()->first();
        $this->assertNotNull($task);

        $this->actingAs($user)
            ->postJson(route('schedules.visit-tasks.store', $schedule), [
                'tasks' => [
                    [
                        'id' => $task->id,
                        'outcome' => 'completed',
                        'notes' => 'Personal care delivered as planned.',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('schedule_visit_tasks', [
            'id' => $task->id,
            'outcome' => 'completed',
            'completed_by_user_id' => $user->id,
        ]);
    }

    public function test_shift_check_in_includes_visit_tasks_for_active_schedule(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-tasks-3',
            'slug' => 'pt-tasks-3',
            'name' => 'Check-In Patient',
        ]);
        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->subMinutes(30),
            'end_at' => now()->addMinutes(30),
        ]);
        seed_schedule_visit_tasks($schedule);

        $this->actingAs($user)
            ->get(route('patients.shift-checkin', $patient->url_key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ShiftCheckIn')
                ->has('visitTasks', count(visit_task_catalogue())));
    }

    public function test_session_start_seeds_tasks_in_response(): void
    {
        $user = User::factory()->create(['primary_role' => 'care_worker', 'email_verified_at' => now()]);
        $patient = Patient::query()->create([
            'url_key' => 'pt-tasks-4',
            'slug' => 'pt-tasks-4',
            'name' => 'ECM Patient',
        ]);
        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->subMinutes(10),
            'end_at' => now()->addMinutes(50),
        ]);

        $this->actingAs($user)
            ->postJson(route('patients.shift-checkin.session.start', $patient->url_key), [
                'schedule_id' => $schedule->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(count(visit_task_catalogue()), 'visit_tasks');
    }
}
