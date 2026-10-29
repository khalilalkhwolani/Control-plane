<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LicenseRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LicenseRevision */
class LicenseRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installation_id' => $this->installation_id,
            'installation' => $this->whenLoaded('installation', fn () => [
                'id' => $this->installation?->id,
                'installation_uuid' => $this->installation?->installation_uuid,
                'site_label' => $this->installation?->site_label,
                'customer' => $this->installation?->relationLoaded('customer')
                    ? $this->installation->customer?->safeSummary()
                    : null,
            ]),
            'license_uuid' => $this->license_uuid,
            'sequence' => $this->sequence,
            'key_id' => $this->key_id,
            'edition' => $this->edition,
            'features' => $this->features,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'grace_until' => $this->grace_until?->toIso8601String(),
            'reason' => $this->reason,
            'issuer' => $this->whenLoaded('issuer', fn () => [
                'id' => $this->issuer?->id,
                'name' => $this->issuer?->name,
                'email' => $this->issuer?->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
