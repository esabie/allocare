<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Support\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->challengeUser($request);
        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'email' => $user->email,
        ]);
    }

    public function store(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $user = $this->challengeUser($request);
        if ($user === null) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $code = (string) $request->input('code');
        $secret = $user->two_factor_secret;

        $valid = $secret && $twoFactor->verifyCode($secret, $code);
        if (! $valid) {
            $valid = $twoFactor->consumeRecoveryCode($user, $code);
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'The authenticator code is invalid.',
            ]);
        }

        return $this->finishLogin($request, $user);
    }

    private function challengeUser(Request $request): ?User
    {
        $userId = $request->session()->get('login.id');

        return $userId ? User::query()->find($userId) : null;
    }

    private function finishLogin(Request $request, User $user): RedirectResponse
    {
        $remember = (bool) $request->session()->get('login.remember', false);

        Auth::login($user, $remember);

        $request->session()->forget(['login.id', 'login.remember', 'two_factor.setup_secret']);
        $request->session()->regenerate();

        $userAgent = (string) $request->userAgent();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_os' => $this->detectOsFromUserAgent($userAgent),
            'last_login_app_version' => $this->detectAppVersion($request),
        ])->save();

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    private function detectOsFromUserAgent(string $userAgent): ?string
    {
        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'iphone'),
            str_contains($ua, 'ipad'),
            str_contains($ua, 'ios') => 'iOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os x'),
            str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'linux') => 'Linux',
            default => null,
        };
    }

    private function detectAppVersion(Request $request): ?string
    {
        $headerVersion = trim((string) $request->header('X-App-Version', ''));
        if ($headerVersion !== '') {
            return $headerVersion;
        }

        $userAgent = (string) $request->userAgent();
        if ($userAgent === '') {
            return null;
        }

        if (preg_match('/(?:allocare|allo care|caregenius|care genius)[\/\s-]*v?(\d+(?:\.\d+){1,3})/i', $userAgent, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
