<?php

namespace App\Http\Middleware;

use App\Support\AuditTrail;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $authUser = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser ? [
                    'id' => $authUser->id,
                    'name' => $authUser->name,
                    'email' => $authUser->email,
                    'first_name' => $authUser->first_name,
                    'surname' => $authUser->surname,
                    'primary_role' => $authUser->primary_role,
                    'roles' => $authUser->roles()->pluck('name')->values(),
                    'photo_path' => $authUser->photo_path,
                    'photoUrl' => $authUser->photo_path ? route('employees.photo', $authUser) : null,
                    'canViewReports' => AuditTrail::canViewReports($authUser),
                    'canViewActivityLog' => AuditTrail::canViewActivityLog($authUser),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'suggest_gdpr_breach' => fn () => $request->session()->get('suggest_gdpr_breach'),
                'gdprBreachPrefill' => fn () => $request->session()->get('gdprBreachPrefill'),
            ],
        ];
    }
}
