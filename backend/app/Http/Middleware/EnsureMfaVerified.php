<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('control_center.mfa_required', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! $user->is_active || ! $user->hasEnrolledMfa()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب إكمال التحقق متعدد العوامل.',
                'code' => 'mfa_required',
            ], 401);
        }

        $verifiedAt = $request->session()->get('mfa.verified_at');
        if (! is_numeric($verifiedAt)) {
            return response()->json([
                'success' => false,
                'message' => 'يجب إكمال التحقق متعدد العوامل.',
                'code' => 'mfa_required',
            ], 401);
        }

        return $next($request);
    }
}
