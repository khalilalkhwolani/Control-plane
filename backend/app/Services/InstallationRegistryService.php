<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Installation;
use App\Models\VendorUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallationRegistryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Installation::query()->with('customer');

        if (filled($filters['search'] ?? null)) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['search']).'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('installation_uuid', 'like', $search)
                    ->orWhere('site_label', 'like', $search);
            });
        }

        if (filled($filters['activation_state'] ?? null)) {
            $query->where('activation_state', $filters['activation_state']);
        }

        if (filled($filters['deployment_mode'] ?? null)) {
            $query->where('deployment_mode', $filters['deployment_mode']);
        }

        if (filled($filters['customer_id'] ?? null)) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        return $query->orderByDesc('id')->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function create(array $data, VendorUser $actor, ?string $reason = null): Installation
    {
        return DB::transaction(function () use ($data, $actor, $reason) {
            $installation = Installation::query()->create([
                'customer_id' => $data['customer_id'],
                'installation_uuid' => Str::lower($data['installation_uuid']),
                'site_label' => $data['site_label'],
                'deployment_mode' => $data['deployment_mode'],
                'activation_state' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $installation->load('customer');
            $this->audit->record(
                'installations.created',
                'high',
                $installation,
                after: $installation->safeSummary(),
                reason: $reason,
                actor: $actor,
            );

            return $installation;
        });
    }

    public function update(Installation $installation, array $data, VendorUser $actor, ?string $reason = null): Installation
    {
        return DB::transaction(function () use ($installation, $data, $actor, $reason) {
            $before = $installation->safeSummary();
            $installation->fill([
                'customer_id' => $data['customer_id'],
                'installation_uuid' => Str::lower($data['installation_uuid']),
                'site_label' => $data['site_label'],
                'deployment_mode' => $data['deployment_mode'],
                'activation_state' => $installation->activation_state,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $actor->id,
            ])->save();
            $installation->load('customer');
            $this->audit->record(
                'installations.updated',
                'high',
                $installation,
                $before,
                $installation->safeSummary(),
                $reason,
                $actor,
            );

            return $installation;
        });
    }

    public function approve(Installation $installation, VendorUser $actor, ?string $reason = null): Installation
    {
        return DB::transaction(function () use ($installation, $actor, $reason): Installation {
            $locked = Installation::query()->lockForUpdate()->findOrFail($installation->id);
            if ($locked->activation_state !== 'pending') {
                throw new \RuntimeException('لا يمكن اعتماد هذا التثبيت من حالته الحالية.');
            }

            $before = $locked->safeSummary();
            $locked->forceFill([
                'activation_state' => 'approved',
                'updated_by' => $actor->id,
            ])->save();
            $locked->load('customer');

            $this->audit->record(
                'installations.approved',
                'high',
                $locked,
                $before,
                $locked->safeSummary(),
                $reason ?? 'اعتماد يدوي بعد مطابقة معرّف التثبيت',
                $actor,
            );

            return $locked;
        });
    }
}
