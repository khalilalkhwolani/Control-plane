<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'ليست لديك صلاحية تنفيذ هذه العملية.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
