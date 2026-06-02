<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineScheduleWritesTest extends TestCase
{
    use RefreshDatabase;

    private function careWorker(): User
    {
        return User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);
    }

    private function patient(): Patient
    {
        return Patient::query()->create([
            'url_key' => 'pt-offline-sched',
            'slug' => 'pt-offline-sched',
            'name' => 'Offline Schedule Patient',
        ]);
    }

    public function test_schedule_store_accepts_json_body_for_offline_sync(): void
    {
        $user = $this->careWorker();
        $patient = $this->patient();

        $this->actingAs($user)
            ->postJson(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $user->id,
                'visit_date' => '2026-05-30',
                'start_time' => '09:00',
                'end_time' => '11:00',
                'purpose' => 'Morning call',
            ])
            ->assertRedirect(route('schedules'));

        $this->assertDatabaseHas('patient_schedules', [
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'purpose' => 'Morning call',
        ]);
    }

    public function test_schedule_complete_accepts_json_patch_for_offline_sync(): void
    {
        $user = $this->careWorker();
        $patient = $this->patient();
        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->from(route('schedules'))
            ->patchJson(route('schedules.complete', $schedule), [
                'notes' => 'Completed while offline',
                'status' => 'completed',
            ])
            ->assertRedirect(route('schedules'));

        $schedule->refresh();
        $this->assertSame('completed', $schedule->status);
        $this->assertSame('Completed while offline', $schedule->notes);
    }

    public function test_schedule_reschedule_accepts_json_patch_for_offline_sync(): void
    {
        $user = $this->careWorker();
        $patient = $this->patient();
        $schedule = PatientSchedule::query()->create([
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'start_at' => '2026-05-24 09:00:00',
            'end_at' => '2026-05-24 11:00:00',
        ]);

        $this->actingAs($user)
            ->patchJson(route('schedules.reschedule', $schedule), [
                'patient_url_key' => $patient->url_key,
                'visit_date' => '2026-05-25',
                'start_time' => '14:00',
                'end_time' => '16:00',
            ])
            ->assertRedirect(route('schedules'));

        $schedule->refresh();
        $this->assertSame('2026-05-25 14:00:00', $schedule->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-25 16:00:00', $schedule->end_at->format('Y-m-d H:i:s'));
    }
}
