<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'N0tGuessable!Pass9',
                'password_confirmation' => 'N0tGuessable!Pass9',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('N0tGuessable!Pass9', $user->refresh()->password));
    }

    public function test_password_update_rejects_reusing_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('N0tGuessable!Pass'),
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'N0tGuessable!Pass',
                'password' => 'N0tGuessable!Pass',
                'password_confirmation' => 'N0tGuessable!Pass',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'N0tGuessable!Pass9',
                'password_confirmation' => 'N0tGuessable!Pass9',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }
}
