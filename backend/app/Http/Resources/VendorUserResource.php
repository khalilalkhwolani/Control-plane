<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VendorUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VendorUser
 */
class VendorUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('role.permissions');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->key,
            'role_label' => $this->role?->name_ar,
            'is_active' => $this->is_active,
            'mfa_enrolled' => $this->hasEnrolledMfa(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'permissions' => $this->role?->permissions->pluck('key')->values() ?? [],
        ];
    }
}
