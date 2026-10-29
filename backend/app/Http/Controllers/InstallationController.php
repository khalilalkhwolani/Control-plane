<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstallationRequest;
use App\Http\Requests\UpdateInstallationRequest;
use App\Http\Resources\InstallationResource;
use App\Models\Installation;
use App\Services\InstallationRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InstallationController extends Controller
{
    public function __construct(private readonly InstallationRegistryService $installations) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->installations->paginate($request->only([
            'search',
            'activation_state',
            'deployment_mode',
            'customer_id',
            'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data' => InstallationResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreInstallationRequest $request): JsonResponse
    {
        $installation = $this->installations->create(
            $request->validated(),
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'data' => new InstallationResource($installation),
        ], 201);
    }

    public function show(Installation $installation): JsonResponse
    {
        $installation->load('customer');

        return response()->json([
            'success' => true,
            'data' => new InstallationResource($installation),
        ]);
    }

    public function update(UpdateInstallationRequest $request, Installation $installation): JsonResponse
    {
        $installation = $this->installations->update(
            $installation,
            $request->validated(),
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'data' => new InstallationResource($installation),
        ]);
    }

    public function approve(Request $request, Installation $installation): JsonResponse
    {
        try {
            $installation = $this->installations->approve(
                $installation,
                $request->user(),
                $request->string('reason')->toString() ?: null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'code' => 'installation_approval_failed',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new InstallationResource($installation),
        ]);
    }
}
