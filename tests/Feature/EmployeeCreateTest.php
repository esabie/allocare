<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_employee(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $email = 'new.employee.'.uniqid().'@example.com';
        $username = 'employee_'.uniqid();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'first_name' => 'Jane',
                'surname' => 'Doe',
                'email' => $email,
                'username' => $username,
                'password' => 'SecurePass1',
                'primary_role' => 'care_worker',
                'date_of_birth' => '1990-05-15',
                'sex' => 'Female',
            ])
            ->assertRedirect(route('employees'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'username' => $username,
            'first_name' => 'Jane',
            'surname' => 'Doe',
        ]);
    }

    public function test_employee_create_accepts_british_date_format(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $email = 'british.dob.'.uniqid().'@example.com';

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'first_name' => 'Sam',
                'surname' => 'Taylor',
                'email' => $email,
                'username' => 'sam_'.uniqid(),
                'password' => 'SecurePass1',
                'date_of_birth' => '20/04/1995',
            ])
            ->assertRedirect(route('employees'));

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'date_of_birth' => '1995-04-20',
        ]);
    }

    public function test_employee_create_rejects_future_date_of_birth(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $futureDob = now()->addYear()->format('Y-m-d');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'first_name' => 'Future',
                'surname' => 'Born',
                'email' => 'future.dob.'.uniqid().'@example.com',
                'username' => 'future_'.uniqid(),
                'password' => 'SecurePass1',
                'date_of_birth' => $futureDob,
            ])
            ->assertSessionHasErrors(['date_of_birth']);
    }

    public function test_employee_create_requires_unique_email_and_username(): void
    {
        $admin = User::factory()->create([
            'primary_role' => 'admin',
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'email' => 'taken@example.com',
            'username' => 'taken_user',
        ]);

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'first_name' => 'Jane',
                'surname' => 'Doe',
                'email' => 'taken@example.com',
                'username' => 'taken_user',
                'password' => 'SecurePass1',
            ])
            ->assertSessionHasErrors(['email', 'username']);
    }
}
