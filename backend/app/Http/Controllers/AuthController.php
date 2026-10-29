<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\MfaChallengeRequest;
use App\Http\Requests\MfaCodeRequest;
use App\Http\Resources\VendorUserResource;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthenticationService $authentication) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authentication->login(
            $request,
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        return response()->json(['success' => true, ...$result]);
    }

    public function enrollStart(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$this->authentication->startEnrollment($request),
        ]);
    }

    public function enrollConfirm(MfaCodeRequest $request): JsonResponse
    {
        $result = $this->authentication->confirmEnrollment($request, (string) $request->validated('code'));

        return response()->json([
            'success' => true,
            'user' => new VendorUserResource($result['user']),
            'recovery_codes' => $result['recovery_codes'],
        ]);
    }

    public function challenge(MfaChallengeRequest $request): JsonResponse
    {
        $user = $this->authentication->challenge(
            $request,
            $request->validated('code'),
            $request->validated('recovery_code'),
        );

        return response()->json([
            'success' => true,
            'user' => new VendorUserResource($user),
        ]);
    }

    public function stepUp(MfaCodeRequest $request): JsonResponse
    {
        $this->authentication->stepUp($request, (string) $request->validated('code'));

        return response()->json(['success' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => new VendorUserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authentication->logout($request);

        return response()->json(['success' => true]);
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $revoked = $this->authentication->revokeOtherSessions($request);

        return response()->json(['success' => true, 'revoked' => $revoked]);
    }
}
