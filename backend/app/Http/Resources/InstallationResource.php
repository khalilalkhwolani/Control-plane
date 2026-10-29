<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Installation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Installation
 */
class InstallationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer?->safeSummary()),
            'installation_uuid' => $this->installation_uuid,
            'site_label' => $this->site_label,
            'deployment_mode' => $this->deployment_mode,
            'activation_state' => $this->activation_state,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
