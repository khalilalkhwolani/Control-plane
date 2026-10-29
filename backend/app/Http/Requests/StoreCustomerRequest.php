<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100', 'unique:customers,reference'],
            'legal_name' => ['required', 'string', 'max:200'],
            'display_name' => ['required', 'string', 'max:200'],
            'contract_state' => ['required', Rule::in(Customer::STATES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:500'],
            'contacts' => ['nullable', 'array', 'max:10'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'max:150'],
            'contacts.*.email' => ['nullable', 'email', 'max:190'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
        ];
    }
}
