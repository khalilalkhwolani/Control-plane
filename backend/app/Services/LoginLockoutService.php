<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class LoginLockoutService
{
    public function assertNotLocked(string $email, string $ip): void
    {
        $payload = $this->payload($email, $ip);
        $lockedUntil = $payload['locked_until'] ?? null;

        if (is_int($lockedUntil) && $lockedUntil > time()) {
            abort(429, 'الحساب مقفل مؤقتاً بعد محاولات دخول فاشلة.');
        }
    }

    public function hitFailure(string $email, string $ip): void
    {
        $payload = $this->payload($email, $ip);
        $attempts = (int) ($payload['attempts'] ?? 0) + 1;
        $max = (int) config('control_center.lockout_max_attempts', 5);
        $minutes = (int) config('control_center.lockout_minutes', 15);

        $next = [
            'attempts' => $attempts,
            'locked_until' => $attempts >= $max ? time() + ($minutes * 60) : null,
        ];

        Cache::put($this->key($email, $ip), $next, now()->addMinutes($minutes * 2));
    }

    public function clear(string $email, string $ip): void
    {
        Cache::forget($this->key($email, $ip));
    }

    /**
     * @return array{attempts?: int, locked_until?: int|null}
     */
    private function payload(string $email, string $ip): array
    {
        $value = Cache::get($this->key($email, $ip), []);

        return is_array($value) ? $value : [];
    }

    private function key(string $email, string $ip): string
    {
        return 'cc-lockout:'.strtolower($email).':'.$ip;
    }
}
