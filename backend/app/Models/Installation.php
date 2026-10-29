<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installation extends Model
{
    public const MODES = ['offline', 'connected'];

    public const ACTIVATION_STATES = ['pending', 'approved', 'suspended'];

    protected $fillable = [
        'customer_id',
        'installation_uuid',
        'site_label',
        'deployment_mode',
        'activation_state',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function licenseRevisions(): HasMany
    {
        return $this->hasMany(LicenseRevision::class)->orderByDesc('sequence');
    }

    public function safeSummary(): array
    {
        return [
            'id' => $this->id,
            'installation_uuid' => $this->installation_uuid,
            'site_label' => $this->site_label,
            'deployment_mode' => $this->deployment_mode,
            'activation_state' => $this->activation_state,
            'customer_id' => $this->customer_id,
        ];
    }
}
