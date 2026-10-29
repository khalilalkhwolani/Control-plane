<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\VendorUserResource;
use App\Models\VendorUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly LoginLockoutService $lockout,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{status: string, user?: array<string, mixed>}
     */
    public function login(Request $request, string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $this->lockout->assertNotLocked($email, (string) $request->ip());

        $user = VendorUser::query()->with('role.permissions')->where('email', $email)->first();

        if ($user === null || ! $user->is_active || ! Hash::check($password, $user->getAuthPassword())) {
            $this->lockout->hitFailure($email, (string) $request->ip());
            $this->audit->record('auth.login_failed', 'medium', reason: 'invalid_credentials', request: $request);
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        $this->lockout->clear($email, (string) $request->ip());
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! (bool) config('control_center.mfa_required', false)) {
            $this->completeAuthenticatedSession($request, $user);
            $this->audit->record('auth.login_succeeded', 'low', $user, reason: 'mfa_deferred', request: $request);

            return [
                'status' => 'authenticated',
                'user' => self::userPayload($user->fresh(['role.permissions'])),
                'mfa_required' => false,
            ];
        }

        $request->session()->put('auth.pending_user_id', $user->id);

        if (! $user->hasEnrolledMfa()) {
            return ['status' => 'mfa_enrollment_required', 'mfa_required' => true];
        }

        return ['status' => 'mfa_required', 'mfa_required' => true];
    }

    /**
     * @return array{otpauth_uri: string, secret: string}
     */
    public function startEnrollment(Request $request): array
    {
        $user = $this->pendingUser($request);

        if ($user->hasEnrolledMfa()) {
            abort(422, 'التحقق متعدد العوامل مفعّل مسبقاً.');
        }

        $secret = $this->totp->generateSecret();
        $request->session()->put('auth.pending_mfa_secret', $secret);

        return [
            'otpauth_uri' => $this->totp->otpauthUri($user->email, $secret),
            'secret' => $secret,
        ];
    }

    /**
     * @return array{user: VendorUser, recovery_codes: list<string>}
     */
    public function confirmEnrollment(Request $request, string $code): array
    {
        $user = $this->pendingUser($request);
        $secret = $request->session()->get('auth.pending_mfa_secret');

        if (! is_string($secret) || $secret === '') {
            abort(422, 'ابدأ تسجيل التحقق متعدد العوامل أولاً.');
        }

        if (! $this->totp->verify($secret, $code)) {
            $this->lockout->hitFailure($user->email, (string) $request->ip());
            throw ValidationException::withMessages([
                'code' => ['رمز التحقق غير صحيح.'],
            ]);
        }

        $recoveryCodes = DB::transaction(function () use ($user, $secret) {
            $user->forceFill([
                'mfa_secret' => $this->totp->encryptSecret($secret),
                'mfa_confirmed_at' => now(),
            ])->save();

            return $this->totp->replaceRecoveryCodes($user);
        });

        $this->completeAuthenticatedSession($request, $user);
        $this->audit->record('auth.mfa_enrolled', 'high', $user, after: $user->safeSummary(), request: $request);

        return [
            'user' => $user->fresh(['role.permissions']),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function challenge(Request $request, ?string $code, ?string $recoveryCode): VendorUser
    {
        $user = $this->pendingUser($request);
        $this->lockout->assertNotLocked($user->email, (string) $request->ip());

        if (! $user->hasEnrolledMfa()) {
            abort(422, 'يجب تسجيل التحقق متعدد العوامل أولاً.');
        }

        $ok = false;
        if (is_string($code) && $code !== '') {
            $ok = $this->totp->verify($this->totp->decryptSecret((string) $user->mfa_secret), $code);
        } elseif (is_string($recoveryCode) && $recoveryCode !== '') {
            $ok = $this->totp->consumeRecoveryCode($user, $recoveryCode);
        }

        if (! $ok) {
            $this->lockout->hitFailure($user->email, (string) $request->ip());
            $this->audit->record('auth.mfa_failed', 'medium', $user, request: $request);
            throw ValidationException::withMessages([
                'code' => ['رمز التحقق غير صحيح.'],
            ]);
        }

        $this->lockout->clear($user->email, (string) $request->ip());
        $this->completeAuthenticatedSession($request, $user);
        $this->audit->record('auth.login_succeeded', 'low', $user, request: $request);

        return $user->fresh(['role.permissions']);
    }

    public function stepUp(Request $request, string $code): void
    {
        $user = $request->user();
        if (! $user instanceof VendorUser || ! $user->hasEnrolledMfa()) {
            abort(401, 'Unauthenticated.');
        }

        if (! $this->totp->verify($this->totp->decryptSecret((string) $user->mfa_secret), $code)) {
            $this->lockout->hitFailure($user->email, (string) $request->ip());
            throw ValidationException::withMessages([
                'code' => ['رمز التحقق غير صحيح.'],
            ]);
        }

        $request->session()->put('mfa.verified_at', now()->timestamp);
        $this->audit->record('auth.mfa_step_up', 'medium', $user, request: $request);
    }

    public function logout(Request $request): void
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user instanceof VendorUser) {
            $this->audit->record('auth.logout', 'low', $user, actor: $user, request: $request);
        }
    }

    public function revokeOtherSessions(Request $request): int
    {
        $user = $request->user();
        if (! $user instanceof VendorUser) {
            abort(401, 'Unauthenticated.');
        }

        $currentId = $request->session()->getId();
        $deleted = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentId)
            ->delete();

        $this->audit->record('auth.sessions_revoked', 'high', $user, after: ['revoked' => $deleted], request: $request);

        return $deleted;
    }

    public function pendingUser(Request $request): VendorUser
    {
        $id = $request->session()->get('auth.pending_user_id');
        $user = is_numeric($id)
            ? VendorUser::query()->with('role.permissions')->find($id)
            : null;

        if (! $user instanceof VendorUser || ! $user->is_active) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }

    private function completeAuthenticatedSession(Request $request, VendorUser $user): void
    {
        Auth::guard('web')->login($user);
        $request->session()->forget(['auth.pending_user_id', 'auth.pending_mfa_secret']);
        $request->session()->regenerate();
        $request->session()->put('mfa.verified_at', now()->timestamp);
        $user->forceFill(['last_login_at' => now()])->save();
    }

    public static function userPayload(VendorUser $user): array
    {
        return (new VendorUserResource($user))->resolve();
    }
}
