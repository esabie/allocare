<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Support\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_challenge_rejects_invalid_code(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->post('/two-factor-challenge', [
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_can_complete_login(): void
    {
        $user = User::factory()->create([
            'two_factor_recovery_codes' => ['ABCD-EFGH'],
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->post('/two-factor-challenge', [
            'code' => 'ABCD-EFGH',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);

        $user->refresh();
        $this->assertSame([], $user->two_factor_recovery_codes);
    }

    public function test_setup_enables_two_factor_and_shows_recovery_codes(): void
    {
        $this->withoutVite();

        $user = User::factory()->withoutTwoFactor()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $setupPage = $this->get(route('two-factor.setup'));
        $setupPage->assertOk();

        $secret = session('two_factor.setup_secret');
        $this->assertIsString($secret);

        $twoFactor = app(TwoFactorAuthentication::class);
        $code = $twoFactor->currentCode($secret);

        $response = $this->post(route('two-factor.setup.store'), [
            'code' => $code,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('two-factor.recovery-codes'));

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertNotEmpty(session('recovery_codes'));
    }

    public function test_authenticated_users_without_two_factor_are_redirected_to_setup(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('two-factor.setup'));
    }
}
