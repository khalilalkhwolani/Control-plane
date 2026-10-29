<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VendorUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class VendorUser extends Authenticatable
{
    /** @use HasFactory<VendorUserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'vendor_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
        'mfa_secret',
        'mfa_confirmed_at',
        'last_login_at',
        'deactivated_at',
        'deactivated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'mfa_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    public function hasEnrolledMfa(): bool
    {
        return $this->mfa_confirmed_at !== null && filled($this->mfa_secret);
    }

    public function hasPermission(string $key): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $this->loadMissing('role');

        if ($this->role?->key === 'owner') {
            return true;
        }

        $this->loadMissing('role.permissions');

        return $this->role?->permissions->contains('key', $key) ?? false;
    }

    public function isOwner(): bool
    {
        return $this->role?->key === 'owner';
    }

    public function safeSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->key,
            'is_active' => $this->is_active,
            'mfa_enrolled' => $this->hasEnrolledMfa(),
        ];
    }
}
