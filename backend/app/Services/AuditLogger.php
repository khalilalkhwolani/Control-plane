<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ControlAuditEvent;
use App\Models\Customer;
use App\Models\Installation;
use App\Models\VendorUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        string $action,
        string $risk,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?VendorUser $actor = null,
        ?Customer $customer = null,
        ?Installation $installation = null,
        ?Request $request = null,
    ): ControlAuditEvent {
        $request ??= request();
        $actor ??= $request?->user();

        return ControlAuditEvent::query()->create([
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'customer_id' => $customer?->id ?? ($subject instanceof Customer ? $subject->id : ($subject instanceof Installation ? $subject->customer_id : null)),
            'installation_id' => $installation?->id ?? ($subject instanceof Installation ? $subject->id : null),
            'request_id' => $this->requestId($request),
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 512) ?: null,
            'risk' => $risk,
            'reason' => $reason,
            'before_json' => $before,
            'after_json' => $after,
            'created_at' => now(),
        ]);
    }

    private function requestId(?Request $request): string
    {
        $header = $request?->header('X-Request-ID');
        if (is_string($header) && $header !== '' && strlen($header) <= 64) {
            return $header;
        }

        return (string) str()->uuid();
    }
}
