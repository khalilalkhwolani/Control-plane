<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFreshMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('control_center.mfa_required', false)) {
            return $next($request);
        }

        $verifiedAt = $request->session()->get('mfa.verified_at');
        $window = (int) config('control_center.mfa_step_up_seconds', 300);

        if (! is_numeric($verifiedAt) || (time() - (int) $verifiedAt) > $window) {
            return response()->json([
                'success' => false,
                'message' => 'أعد إدخال رمز التحقق متعدد العوامل لمتابعة هذا الإجراء.',
                'code' => 'mfa_step_up_required',
            ], 403);
        }

        return $next($request);
    }
}
