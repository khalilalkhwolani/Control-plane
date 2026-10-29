<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\VendorUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorUserAdminService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = VendorUser::query()->with('role.permissions');

        if (filled($filters['search'] ?? null)) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['search']).'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)->orWhere('email', 'like', $search);
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function create(array $data, VendorUser $actor, ?string $reason = null): VendorUser
    {
        $role = Role::query()->where('key', $data['role'])->firstOrFail();

        return DB::transaction(function () use ($data, $actor, $reason, $role) {
            $user = VendorUser::query()->create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role_id' => $role->id,
                'is_active' => true,
            ]);
            $user->load('role.permissions');
            $this->audit->record('users.created', 'high', $user, after: $user->safeSummary(), reason: $reason, actor: $actor);

            return $user;
        });
    }

    public function update(VendorUser $user, array $data, VendorUser $actor, ?string $reason = null): VendorUser
    {
        if ($user->isOwner()) {
            throw ValidationException::withMessages([
                'role' => ['حساب المالك لا يُدار من واجهة المستخدم.'],
            ]);
        }

        if ($user->id === $actor->id && empty($data['is_active'])) {
            throw ValidationException::withMessages([
                'is_active' => ['لا يمكنك تعطيل حسابك.'],
            ]);
        }

        $role = Role::query()->where('key', $data['role'])->firstOrFail();

        return DB::transaction(function () use ($user, $data, $actor, $reason, $role) {
            $before = $user->safeSummary();
            $payload = [
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'role_id' => $role->id,
                'is_active' => (bool) $data['is_active'],
                'deactivated_at' => $data['is_active'] ? null : now(),
                'deactivated_by' => $data['is_active'] ? null : $actor->id,
            ];
            if (filled($data['password'] ?? null)) {
                $payload['password'] = $data['password'];
            }
            $user->fill($payload)->save();
            $user->load('role.permissions');
            $this->audit->record('users.updated', 'high', $user, $before, $user->safeSummary(), $reason, $actor);

            return $user;
        });
    }
}
