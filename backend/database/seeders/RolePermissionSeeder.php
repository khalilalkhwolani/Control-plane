<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'overview.view' => 'عرض الرئيسية',
            'customers.view' => 'عرض العملاء',
            'customers.create' => 'إنشاء عميل',
            'customers.update' => 'تعديل عميل',
            'installations.view' => 'عرض التثبيتات',
            'installations.create' => 'إنشاء تثبيت',
            'installations.update' => 'تعديل تثبيت',
            'licenses.view' => 'عرض التراخيص',
            'licenses.issue' => 'إصدار ترخيص',
            'users.view' => 'عرض مستخدمي اللوحة',
            'users.create' => 'إنشاء مستخدم لوحة',
            'users.update' => 'تعديل مستخدم لوحة',
            'audit.view' => 'عرض سجل التدقيق',
            'sessions.revoke' => 'إلغاء الجلسات',
        ];

        $permissionIds = [];
        foreach ($permissions as $key => $nameAr) {
            $permissionIds[$key] = Permission::query()->updateOrCreate(
                ['key' => $key],
                ['name_ar' => $nameAr],
            )->id;
        }

        $roles = [
            'owner' => ['مالك', 'Owner', array_keys($permissions)],
            'license_administrator' => ['مسؤول التراخيص', 'License administrator', [
                'overview.view', 'customers.view', 'customers.create', 'customers.update',
                'installations.view', 'installations.create', 'installations.update',
                'licenses.view', 'licenses.issue',
            ]],
            'support_engineer' => ['مهندس الدعم', 'Support engineer', [
                'overview.view', 'customers.view', 'installations.view',
                'licenses.view',
            ]],
            'release_manager' => ['مدير الإصدارات', 'Release manager', [
                'overview.view',
            ]],
            'auditor' => ['مدقق', 'Auditor', [
                'overview.view', 'customers.view', 'installations.view', 'users.view', 'audit.view',
                'licenses.view',
            ]],
        ];

        foreach ($roles as $key => [$nameAr, $nameEn, $keys]) {
            $role = Role::query()->updateOrCreate(
                ['key' => $key],
                ['name_ar' => $nameAr, 'name_en' => $nameEn],
            );
            $role->permissions()->sync(array_map(fn (string $permissionKey) => $permissionIds[$permissionKey], $keys));
        }
    }
}
