<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ControlAuditEvent;
use App\Models\Installation;
use App\Models\LicenseRevision;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'customers_count' => Customer::query()->count(),
                'installations_count' => Installation::query()->count(),
                'pending_activations' => Installation::query()->where('activation_state', 'pending')->count(),
                'licenses_count' => LicenseRevision::query()->count(),
                'expiring_licenses' => LicenseRevision::query()
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(30)])
                    ->count(),
                'unacknowledged_files' => 0,
                'open_support_sessions' => 0,
                'security_alerts' => 0,
                'recent_audit' => ControlAuditEvent::query()
                    ->orderByDesc('id')
                    ->limit(8)
                    ->get(['id', 'action', 'actor_email', 'risk', 'created_at', 'subject_type', 'subject_id']),
                'deferred' => [
                    'activations' => 'مرحلة لاحقة: رمز تهيئة لمرة واحدة.',
                    'support' => 'مرحلة لاحقة: جلسات دعم مؤقتة.',
                    'releases' => 'مرحلة لاحقة: إصدارات موقعة.',
                ],
            ],
        ]);
    }
}
