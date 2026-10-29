<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ControlAuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ControlAuditEvent::query()->orderByDesc('id');

        if (filled($request->query('action'))) {
            $query->where('action', $request->query('action'));
        }
        if (filled($request->query('actor_email'))) {
            $query->where('actor_email', 'like', '%'.$request->query('actor_email').'%');
        }
        if (filled($request->query('customer_id'))) {
            $query->where('customer_id', (int) $request->query('customer_id'));
        }
        if (filled($request->query('request_id'))) {
            $query->where('request_id', $request->query('request_id'));
        }
        if (filled($request->query('from'))) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if (filled($request->query('to'))) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $page = $query->paginate(min((int) $request->query('per_page', 30), 100));

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
