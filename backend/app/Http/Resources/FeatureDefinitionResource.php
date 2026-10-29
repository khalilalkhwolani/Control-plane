<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FeatureDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeatureDefinition */
class FeatureDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name_ar' => $this->name_ar,
            'description_ar' => $this->description_ar,
            'is_active' => $this->is_active,
        ];
    }
}
