<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorSetupController extends Controller
{
    public function create(Request $request, TwoFactorAuthentication $twoFactor): Response|RedirectResponse
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasTwoFactorEnabled()) {
            if ($request->user()) {
                return redirect()->route('dashboard');
            }

            return redirect()->route('two-factor.login');
        }

        $secret = $request->session()->get('two_factor.setup_secret');
        if (! is_string($secret) || $secret === '') {
            $secret = $twoFactor->generateSecret();
            $request->session()->put('two_factor.setup_secret', $secret);
        }

        $otpAuthUrl = $twoFactor->otpAuthUrl($user, $secret);

        return Inertia::render('Auth/TwoFactorSetup', [
            'email' => $user->email,
            'secret' => $secret,
            'qrCodeSvg' => $twoFactor->qrCodeSvg($otpAuthUrl),
            'otpAuthUrl' => $otpAuthUrl,
        ]);
    }

    public function store(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $user = $this->resolveUser($request);
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        $secret = $request->session()->get('two_factor.setup_secret');
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'code' => 'Your setup session expired. Refresh the page and scan the QR code again.',
            ]);
        }

        if (! $twoFactor->verifyCode($secret, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'The authenticator code is invalid. Check the code and try again.',
            ]);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        $twoFactor->confirmSetup($user, $secret, $recoveryCodes);

        $remember = (bool) $request->session()->get('login.remember', false);
        Auth::login($user, $remember);

        $request->session()->forget(['login.id', 'login.remember', 'two_factor.setup_secret']);
        $request->session()->regenerate();
        $request->session()->flash('recovery_codes', $recoveryCodes);

        return redirect()->route('two-factor.recovery-codes');
    }

    private function resolveUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $userId = $request->session()->get('login.id');

        return $userId ? User::query()->find($userId) : null;
    }
}
