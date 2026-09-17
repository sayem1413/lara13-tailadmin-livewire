<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permissions granted to the "Admin" role in addition to "Super Admin",
     * which bypasses all authorization checks (see AppServiceProvider).
     *
     * @var array<int, string>
     */
    protected array $adminPermissions = [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'activity-log.view',
        'settings.view',
        'settings.update',
    ];

    /**
     * Every permission the common modules understand out of the box.
     *
     * @var array<int, string>
     */
    protected array $allPermissions = [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'activity-log.view',
        'settings.view',
        'settings.update',
    ];

    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        foreach ($this->allPermissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // findOrCreate() writes straight to the database without refreshing the
        // registrar's in-memory permission cache, so the newly created rows
        // above are invisible to syncPermissions() below until we clear it.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('Super Admin')->syncPermissions($this->allPermissions);
        Role::findOrCreate('Admin')->syncPermissions($this->adminPermissions);
    }
}
