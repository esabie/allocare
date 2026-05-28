<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'string', 'max:50'],
            'sex' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'home_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
        ]);

        $normalizedDateOfBirth = $this->normalizeDateOfBirth($validated['date_of_birth'] ?? null);

        $fullName = trim($validated['first_name'].' '.$validated['surname']);

        $user = User::query()->create([
            'name' => $fullName,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'title' => $validated['title'] ?? null,
            'first_name' => $validated['first_name'],
            'surname' => $validated['surname'],
            'date_of_birth' => $normalizedDateOfBirth,
            'sex' => $validated['sex'] ?? null,
            'username' => $validated['username'],
            'home_address' => $validated['home_address'] ?? null,
            'city' => $validated['city'] ?? null,
            'postcode' => $validated['postcode'] ?? null,
            'primary_role' => 'super_admin',
            'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('employee-photos', 'public') : null,
            'mfa_enabled' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }

    private function normalizeDateOfBirth(?string $raw): ?string
    {
        $rawDateOfBirth = trim((string) $raw);
        if ($rawDateOfBirth === '') {
            return null;
        }

        $acceptedFormats = ['d/m/Y', 'Y-m-d'];
        foreach ($acceptedFormats as $format) {
            try {
                $parsedDate = Carbon::createFromFormat($format, $rawDateOfBirth);
                if ($parsedDate !== false && $parsedDate->format($format) === $rawDateOfBirth) {
                    return $parsedDate->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Try the next accepted format.
            }
        }

        throw ValidationException::withMessages([
            'date_of_birth' => 'Use DD/MM/YYYY or YYYY-MM-DD for date of birth.',
        ]);
    }
}
