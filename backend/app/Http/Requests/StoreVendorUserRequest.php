<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = (int) config('control_center.password_min_length', 12);

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:vendor_users,email'],
            'password' => ['required', 'string', 'min:'.$min, 'confirmed'],
            'role' => ['required', Rule::in(['license_administrator', 'support_engineer', 'release_manager', 'auditor'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
