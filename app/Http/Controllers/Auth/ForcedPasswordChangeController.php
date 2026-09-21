<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ForcedPasswordChangeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->mustResetPassword()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ForcePasswordChange');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->mustResetPassword()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Your new password must be different from your current password.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_reset_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('status', 'Your password has been updated.');
    }
}
