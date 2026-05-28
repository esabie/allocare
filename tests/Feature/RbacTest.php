<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
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
}
