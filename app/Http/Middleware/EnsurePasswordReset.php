<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordReset
{
    /**
     * @var array<int, string>
     */
    private const EXEMPT_ROUTE_NAMES = [
        'login',
        'logout',
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
        'password.force-change',
        'password.force-change.store',
        'register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null || ! $user->mustResetPassword()) {
            return $next($request);
        }

        return redirect()->route('password.force-change');
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs(self::EXEMPT_ROUTE_NAMES)) {
            return true;
        }

        return $request->is('logout');
    }
}
