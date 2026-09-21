<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ForcePasswordResetNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForcePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_password_reset_command_flags_users_and_sends_emails(): void
    {
        Notification::fake();

        $user = User::factory()->create(['must_reset_password' => false]);

        $this->artisan('users:force-password-reset')
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->must_reset_password);
        Notification::assertSentTo($user, ForcePasswordResetNotification::class);
    }

    public function test_force_password_reset_command_dry_run_flags_without_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['must_reset_password' => false]);

        $this->artisan('users:force-password-reset', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->must_reset_password);
        Notification::assertNothingSent();
    }

    public function test_user_with_reset_flag_is_redirected_to_force_change(): void
    {
        $user = User::factory()->create(['must_reset_password' => true]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.force-change'));
    }

    public function test_force_change_updates_password_and_clears_flag(): void
    {
        $user = User::factory()->create(['must_reset_password' => true]);

        $this->actingAs($user)
            ->post(route('password.force-change.store'), [
                'current_password' => 'password',
                'password' => 'N0tGuessable!Pass',
                'password_confirmation' => 'N0tGuessable!Pass',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_reset_password);
        $this->assertTrue(Hash::check('N0tGuessable!Pass', $user->password));
    }

    public function test_force_change_rejects_weak_password(): void
    {
        $user = User::factory()->create(['must_reset_password' => true]);

        $this->actingAs($user)
            ->from(route('password.force-change'))
            ->post(route('password.force-change.store'), [
                'current_password' => 'password',
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('password.force-change'));

        $this->assertTrue($user->refresh()->must_reset_password);
    }

    public function test_email_reset_clears_must_reset_password_flag(): void
    {
        Notification::fake();

        $user = User::factory()->create(['must_reset_password' => true]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'N0tGuessable!Pass',
                'password_confirmation' => 'N0tGuessable!Pass',
            ])->assertRedirect(route('login'));

            $this->assertFalse($user->refresh()->must_reset_password);

            return true;
        });
    }

    public function test_force_change_rejects_reusing_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('N0tGuessable!Pass'),
            'must_reset_password' => true,
        ]);

        $this->actingAs($user)
            ->from(route('password.force-change'))
            ->post(route('password.force-change.store'), [
                'current_password' => 'N0tGuessable!Pass',
                'password' => 'N0tGuessable!Pass',
                'password_confirmation' => 'N0tGuessable!Pass',
            ])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('password.force-change'));

        $this->assertTrue($user->refresh()->must_reset_password);
    }

    public function test_email_reset_rejects_reusing_current_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('N0tGuessable!Pass'),
            'must_reset_password' => true,
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->from('/reset-password/'.$notification->token)
                ->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'N0tGuessable!Pass',
                    'password_confirmation' => 'N0tGuessable!Pass',
                ])
                ->assertSessionHasErrors('password');

            $this->assertTrue($user->refresh()->must_reset_password);

            return true;
        });
    }

    public function test_email_reset_rejects_weak_password(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->from('/reset-password/'.$notification->token)
                ->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ])
                ->assertSessionHasErrors('password');

            return true;
        });
    }
}
