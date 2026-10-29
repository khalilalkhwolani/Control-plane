<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LicenseRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'installation_id', 'license_uuid', 'sequence', 'key_id', 'edition',
        'features', 'issued_at', 'expires_at', 'grace_until', 'document_sha256',
        'signed_document', 'issued_by', 'reason', 'created_at',
    ];

    protected $hidden = ['signed_document'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'grace_until' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booting(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Issued license revisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Issued license revisions are immutable.');
        });
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'issued_by');
    }
}
