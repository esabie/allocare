<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    /**
     * @var array<int, string>
     */
    private const EXEMPT_ROUTE_NAMES = [
        'login',
        'two-factor.login',
        'two-factor.login.store',
        'two-factor.setup',
        'two-factor.setup.store',
        'two-factor.recovery-codes',
        'two-factor.recovery-codes.store',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        return redirect()->route('two-factor.setup');
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs(self::EXEMPT_ROUTE_NAMES)) {
            return true;
        }

        return $request->is('logout');
    }
}
