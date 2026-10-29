<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RecoveryCode;
use App\Models\VendorUser;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

class TotpService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpauthUri(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('control_center.mfa_issuer'),
            $email,
            $secret,
        );
    }

    public function verify(string $secret, string $code): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $normalized, 1) === true;
    }

    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function decryptSecret(string $payload): string
    {
        return Crypt::decryptString($payload);
    }

    /**
     * @return list<string>
     */
    public function replaceRecoveryCodes(VendorUser $user): array
    {
        $user->recoveryCodes()->delete();
        $count = (int) config('control_center.recovery_code_count', 8);
        $plain = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(Str::random(4).'-'.Str::random(4));
            $plain[] = $code;
            $user->recoveryCodes()->create([
                'code_hash' => Hash::make($code),
            ]);
        }

        return $plain;
    }

    public function consumeRecoveryCode(VendorUser $user, string $code): bool
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return false;
        }

        $unused = $user->recoveryCodes()->whereNull('used_at')->get();

        foreach ($unused as $stored) {
            /** @var RecoveryCode $stored */
            if (Hash::check($normalized, $stored->code_hash)) {
                $stored->forceFill(['used_at' => now()])->save();

                return true;
            }
        }

        return false;
    }

    public function currentOtp(string $secret): string
    {
        $otp = $this->google2fa->getCurrentOtp($secret);
        if (! is_string($otp) || $otp === '') {
            throw new RuntimeException('Unable to generate a TOTP code.');
        }

        return $otp;
    }
}
