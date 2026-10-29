<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Installation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $installation = $this->route('installation');
        $id = $installation instanceof Installation ? $installation->id : $installation;

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'installation_uuid' => ['required', 'uuid', Rule::unique('installations', 'installation_uuid')->ignore($id)],
            'site_label' => ['required', 'string', 'max:150'],
            'deployment_mode' => ['required', Rule::in(Installation::MODES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
