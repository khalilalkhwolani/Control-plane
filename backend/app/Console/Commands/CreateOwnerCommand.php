<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\VendorUser;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateOwnerCommand extends Command
{
    protected $signature = 'control-center:create-owner
        {email : Owner email}
        {--name=المالك : Display name}
        {--password= : Password (prompted when omitted)}';

    protected $description = 'Create the first Pro One Control Center owner. MFA enrollment happens on first login.';

    public function handle(AuditLogger $audit): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = (string) $this->option('name');
        $password = $this->option('password');
        $min = (int) config('control_center.password_min_length', 12);

        if (! is_string($password) || $password === '') {
            $password = $this->secret('Password');
        }

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email', 'max:190'],
                'name' => ['required', 'string', 'max:150'],
                'password' => ['required', 'string', 'min:'.$min],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (VendorUser::query()->whereHas('role', fn ($query) => $query->where('key', 'owner'))->exists()) {
            $this->error('An owner account already exists.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);

        $role = Role::query()->where('key', 'owner')->firstOrFail();

        $owner = VendorUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $audit->record('users.owner_created', 'high', $owner, after: $owner->safeSummary());

        $this->info("Owner [{$owner->email}] created. Complete TOTP enrollment on first login.");

        return self::SUCCESS;
    }
}
