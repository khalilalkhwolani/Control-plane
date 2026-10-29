<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ControlAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_email',
        'action',
        'subject_type',
        'subject_id',
        'customer_id',
        'installation_id',
        'request_id',
        'ip_address',
        'user_agent',
        'risk',
        'reason',
        'before_json',
        'after_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booting(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Audit events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Audit events are append-only.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'actor_id');
    }
}
