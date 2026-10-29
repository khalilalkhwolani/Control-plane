<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('vendor_user');
        $id = is_object($user) ? $user->id : $user;
        $min = (int) config('control_center.password_min_length', 12);

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('vendor_users', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'min:'.$min, 'confirmed'],
            'role' => ['required', Rule::in(['license_administrator', 'support_engineer', 'release_manager', 'auditor'])],
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
