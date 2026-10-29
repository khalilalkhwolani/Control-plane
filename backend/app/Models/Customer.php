<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public const STATES = ['prospect', 'active', 'suspended', 'ended'];

    protected $fillable = [
        'reference',
        'legal_name',
        'display_name',
        'contract_state',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function installations(): HasMany
    {
        return $this->hasMany(Installation::class);
    }

    public function licenseRevisions(): HasMany
    {
        return $this->hasManyThrough(LicenseRevision::class, Installation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'created_by');
    }

    public function safeSummary(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'legal_name' => $this->legal_name,
            'display_name' => $this->display_name,
            'contract_state' => $this->contract_state,
        ];
    }
}
