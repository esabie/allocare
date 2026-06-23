<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthentication
{
    public const DEMO_SECRET = 'JBSWY3DPEHPK3PXP';

    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpAuthUrl(User $user, string $secret): string
    {
        $issuer = config('app.name', 'Allocare');

        return $this->google2fa->getQRCodeUrl($issuer, $user->email, $secret);
    }

    public function qrCodeSvg(string $otpAuthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($otpAuthUrl);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';

        if ($normalized === '' || ! ctype_digit($normalized)) {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $normalized, 1);
    }

    public function currentCode(string $secret): string
    {
        return $this->google2fa->getCurrentOtp($secret);
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => strtoupper(Str::random(4).'-'.Str::random(4)))
            ->values()
            ->all();
    }

    public function confirmSetup(User $user, string $secret, array $recoveryCodes): void
    {
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
            'mfa_enabled' => true,
        ])->save();
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = strtoupper(trim(str_replace(' ', '', $code)));
        $codes = $user->two_factor_recovery_codes ?? [];

        $index = collect($codes)->search(fn (string $candidate) => hash_equals(strtoupper($candidate), $normalized));
        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill([
            'two_factor_recovery_codes' => array_values($codes),
        ])->save();

        return true;
    }

    public function reset(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'mfa_enabled' => true,
        ])->save();
    }
}
