<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureDefinition extends Model
{
    protected $fillable = ['key', 'name_ar', 'description_ar', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
