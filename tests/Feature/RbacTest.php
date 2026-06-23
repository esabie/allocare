<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_role_from_structured_rbac_relation(): void
    {
        $user = User::factory()->create([
            'primary_role' => null,
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'admin',
        ], [
            'description' => 'Admin role',
            'is_system' => true,
        ]);

        $user->roles()->attach($role->id);

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_admin_cannot_edit_employee_profile(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
            'mfa_enabled' => true,
        ]);

        $employee = User::factory()->create([
            'first_name' => 'Staff',
            'surname' => 'Member',
            'email_verified_at' => now(),
            'mfa_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'first_name' => 'Updated',
                'surname' => 'Name',
                'email' => $employee->email,
            ])
            ->assertForbidden();
    }

    public function test_care_manager_can_edit_employee_profile(): void
    {
        $careManager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
            'mfa_enabled' => true,
        ]);

        $employee = User::factory()->create([
            'first_name' => 'Staff',
            'surname' => 'Member',
            'email_verified_at' => now(),
            'mfa_enabled' => true,
        ]);

        $this->actingAs($careManager)
            ->put(route('employees.update', $employee), [
                'first_name' => 'Updated',
                'surname' => 'Name',
                'email' => $employee->email,
            ])
            ->assertRedirect(route('employees.profile', $employee));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'first_name' => 'Updated',
            'surname' => 'Name',
        ]);
    }

    public function test_care_worker_is_blocked_from_manager_only_areas(): void
    {
        $worker = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($worker)->get(route('analytics'))->assertForbidden();
        $this->actingAs($worker)->get(route('employees'))->assertForbidden();
        $this->actingAs($worker)->get(route('patients.create'))->assertForbidden();
        $this->actingAs($worker)->get(route('reports'))->assertForbidden();

        $patient = Patient::query()->create([
            'url_key' => 'pt-rbac-sched',
            'slug' => 'pt-rbac-sched',
            'name' => 'Schedule Patient',
        ]);

        $this->actingAs($worker)
            ->post(route('schedules.store'), [
                'patient_url_key' => $patient->url_key,
                'assigned_user_id' => $worker->id,
                'visit_date' => now()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_cannot_view_reports_hub_or_analytics(): void
    {
        $supervisor = User::factory()->create([
            'primary_role' => 'supervisor',
            'email_verified_at' => now(),
        ]);

        $this->assertTrue(Rbac::canEscalateIncidents($supervisor));
        $this->assertFalse(Rbac::canViewReports($supervisor));
        $this->assertTrue(Rbac::canCountersignControlledDrugs($supervisor));

        $this->actingAs($supervisor)->get(route('reports'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('analytics'))->assertForbidden();
    }

    public function test_care_manager_can_access_reports_and_register_patients(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $this->assertTrue(Rbac::canViewReports($manager));
        $this->assertTrue(Rbac::canRegisterPatients($manager));
        $this->assertTrue(Rbac::canManageRostering($manager));

        $this->actingAs($manager)->get(route('reports'))->assertOk();
        $this->actingAs($manager)->get(route('patients.create'))->assertOk();
    }

    public function test_mar_witness_staff_excludes_care_workers(): void
    {
        User::factory()->create(['primary_role' => 'care_worker', 'name' => 'Worker Witness']);
        User::factory()->create(['primary_role' => 'supervisor', 'name' => 'Supervisor Witness']);

        $witnesses = list_mar_witness_staff();
        $names = collect($witnesses)->pluck('name')->all();

        $this->assertNotContains('Worker Witness', $names);
        $this->assertContains('Supervisor Witness', $names);
    }
}
