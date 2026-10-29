<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\VendorUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerRegistryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Customer::query()->with('contacts')->withCount('installations');

        if (filled($filters['search'] ?? null)) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['search']).'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('reference', 'like', $search)
                    ->orWhere('legal_name', 'like', $search)
                    ->orWhere('display_name', 'like', $search);
            });
        }

        if (filled($filters['contract_state'] ?? null)) {
            $query->where('contract_state', $filters['contract_state']);
        }

        return $query->orderBy('display_name')->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function create(array $data, VendorUser $actor, ?string $reason = null): Customer
    {
        return DB::transaction(function () use ($data, $actor, $reason) {
            $customer = Customer::query()->create([
                'reference' => $data['reference'],
                'legal_name' => $data['legal_name'],
                'display_name' => $data['display_name'],
                'contract_state' => $data['contract_state'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->syncContacts($customer, $data['contacts'] ?? []);
            $customer->load('contacts');
            $this->audit->record('customers.created', 'medium', $customer, after: $customer->safeSummary(), reason: $reason, actor: $actor);

            return $customer;
        });
    }

    public function update(Customer $customer, array $data, VendorUser $actor, ?string $reason = null): Customer
    {
        return DB::transaction(function () use ($customer, $data, $actor, $reason) {
            $before = $customer->safeSummary();
            $customer->fill([
                'reference' => $data['reference'],
                'legal_name' => $data['legal_name'],
                'display_name' => $data['display_name'],
                'contract_state' => $data['contract_state'],
                'notes' => $data['notes'] ?? null,
                'updated_by' => $actor->id,
            ])->save();
            $this->syncContacts($customer, $data['contacts'] ?? []);
            $customer->load('contacts');
            $this->audit->record('customers.updated', 'medium', $customer, $before, $customer->safeSummary(), $reason, $actor);

            return $customer;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $contacts
     */
    private function syncContacts(Customer $customer, array $contacts): void
    {
        $customer->contacts()->delete();
        foreach ($contacts as $contact) {
            $customer->contacts()->create([
                'name' => $contact['name'],
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'is_primary' => (bool) ($contact['is_primary'] ?? false),
            ]);
        }
    }
}
