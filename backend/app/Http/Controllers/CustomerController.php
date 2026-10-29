<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerRegistryService $customers) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->customers->paginate($request->only(['search', 'contract_state', 'per_page']));

        return response()->json([
            'success' => true,
            'data' => CustomerResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create(
            $request->validated(),
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['contacts', 'installations']);

        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customers->update(
            $customer,
            $request->validated(),
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer),
        ]);
    }
}
