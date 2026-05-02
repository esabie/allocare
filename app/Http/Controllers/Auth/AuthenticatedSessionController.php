<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
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

        // Accept app-like user agents such as "AlloCare/3.18.0" or "AlloCare 3.18.0".
        if (preg_match('/(?:allocare|allo care|caregenius|care genius)[\/\s-]*v?(\d+(?:\.\d+){1,3})/i', $userAgent, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();
        if ($user) {
            $userAgent = (string) $request->userAgent();
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_os' => $this->detectOsFromUserAgent($userAgent),
                'last_login_app_version' => $this->detectAppVersion($request),
            ])->save();
        }

        $request->session()->regenerate();

        return redirect(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
