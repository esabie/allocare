<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reset_employee_two_factor(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $employee = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('employees.reset-two-factor', $employee))
            ->assertRedirect(route('employees.profile', $employee));

        $employee->refresh();
        $this->assertFalse($employee->hasTwoFactorEnabled());
        $this->assertNull($employee->two_factor_secret);
        $this->assertNull($employee->two_factor_confirmed_at);
    }

    public function test_care_manager_cannot_reset_employee_two_factor(): void
    {
        $manager = User::factory()->create([
            'primary_role' => 'care_manager',
            'email_verified_at' => now(),
        ]);

        $employee = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('employees.reset-two-factor', $employee))
            ->assertForbidden();

        $this->assertTrue($employee->fresh()->hasTwoFactorEnabled());
    }

    public function test_reset_employee_must_complete_setup_on_next_login(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $employee = User::factory()->create([
            'primary_role' => 'care_worker',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('employees.reset-two-factor', $employee));

        $this->post('/logout');

        $this->post('/login', [
            'email' => $employee->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.setup'));
    }
}
