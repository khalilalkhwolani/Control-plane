<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\FeatureDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'edition' => ['required', 'string', 'regex:/^[a-z][a-z0-9._-]*$/', 'max:100'],
            'features' => ['present', 'array'],
            'features.*' => ['string', Rule::exists(FeatureDefinition::class, 'key')->where('is_active', true)],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
