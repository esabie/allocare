<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorRecoveryCodesController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'recoveryCodes' => $codes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->session()->forget('recovery_codes');

        return redirect()->route('dashboard');
    }
}
