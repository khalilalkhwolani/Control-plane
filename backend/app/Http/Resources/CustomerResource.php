<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'legal_name' => $this->legal_name,
            'display_name' => $this->display_name,
            'contract_state' => $this->contract_state,
            'notes' => $this->notes,
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => $contact->is_primary,
            ])->values()),
            'installations_count' => $this->whenCounted('installations'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
