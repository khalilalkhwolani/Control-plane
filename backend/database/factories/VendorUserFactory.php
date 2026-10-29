<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\VendorUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorUser>
 */
class VendorUserFactory extends Factory
{
    protected $model = VendorUser::class;

    public function definition(): array
    {
        $roleId = Role::query()->where('key', 'auditor')->value('id');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password12chars',
            'role_id' => $roleId,
            'is_active' => true,
        ];
    }
}
