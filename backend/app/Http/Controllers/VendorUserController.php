<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorUserRequest;
use App\Http\Requests\UpdateVendorUserRequest;
use App\Http\Resources\VendorUserResource;
use App\Models\VendorUser;
use App\Services\VendorUserAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorUserController extends Controller
{
    public function __construct(private readonly VendorUserAdminService $users) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->users->paginate($request->only(['search', 'is_active', 'per_page']));

        return response()->json([
            'success' => true,
            'data' => VendorUserResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreVendorUserRequest $request): JsonResponse
    {
        $user = $this->users->create(
            $request->validated(),
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'data' => new VendorUserResource($user),
        ], 201);
    }

    public function update(UpdateVendorUserRequest $request, VendorUser $vendorUser): JsonResponse
    {
        $user = $this->users->update(
            $vendorUser,
            $request->validated(),
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'data' => new VendorUserResource($user),
        ]);
    }
}
