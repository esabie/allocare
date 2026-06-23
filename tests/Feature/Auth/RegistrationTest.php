<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_register_path_redirects_to_login(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/registeriw54w69w46gw45wggw5w4');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/registeriw54w69w46gw45wggw5w4', [
            'title' => 'Dr',
            'first_name' => 'Test',
            'surname' => 'User',
            'date_of_birth' => '1990-05-15',
            'sex' => 'Female',
            'email' => 'test@example.com',
            'home_address' => '1 Care Street',
            'city' => 'London',
            'postcode' => 'EC1A 1BB',
            'username' => 'tuser_reg',
            'password' => 'N0tGuessable!Pass',
            'password_confirmation' => 'N0tGuessable!Pass',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('two-factor.setup'));

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Test User', $user->name);
        $this->assertSame('super_admin', $user->primary_role);
        $this->assertSame('tuser_reg', $user->username);
        $this->assertSame('1990-05-15', $user->date_of_birth);
        $this->assertTrue($user->mfa_enabled);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_registration_always_requires_authenticator_setup(): void
    {
        $this->post('/registeriw54w69w46gw45wggw5w4', [
            'first_name' => 'No',
            'surname' => 'Mfa',
            'email' => 'nomfa@example.com',
            'username' => 'nomfa_user',
            'password' => 'N0tGuessable!Pass',
            'password_confirmation' => 'N0tGuessable!Pass',
        ]);

        $user = User::query()->where('email', 'nomfa@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->mfa_enabled);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }
}
