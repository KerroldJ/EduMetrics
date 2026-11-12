<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PermissionRegistrar $permissionRegistrar): void
    {
        $rolePermissions = [
            'Admin' => [
                'dashboard.view',
                'users.manage',
                'roles.assign',
                'strands.manage',
                'assignments.manage',
                'forms.manage',
                'periods.manage',
                'evaluations.monitor',
                'results.view-all',
                'results.release',
                'reports.generate',
                'reports.download',
            ],
            'Teacher' => [
                'dashboard.view',
                'results.view-own',
            ],
            'Student' => [
                'dashboard.view',
                'evaluations.submit',
            ],
        ];

        $permissionRegistrar->forgetCachedPermissions();

        foreach (array_unique(array_merge(...array_values($rolePermissions))) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $permissionRegistrar->forgetCachedPermissions();

        foreach ($rolePermissions as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        $permissionRegistrar->forgetCachedPermissions();
    }
}
