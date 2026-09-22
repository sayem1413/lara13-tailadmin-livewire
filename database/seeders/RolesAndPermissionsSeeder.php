<?php

namespace Database\Seeders;

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
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
        'admin.users.index',
        'admin.users.create',
        'admin.users.edit',
        'admin.users.destroy',
        'admin.users.restore',
        'admin.users.force-delete',
        'admin.activity-log.index',
        'admin.settings.edit',
        'admin.settings.update',
        'admin.notifications.preferences.edit',
        'admin.media.index',
        'admin.media.destroy',
        'admin.users.import',
        'admin.users.export',
    ];

    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        // Discovers a permission for every named "admin.*" route - index/
        // create/edit for each module currently built.
        Artisan::call('permissions:sync');

        foreach (Permission::MANUAL_PERMISSIONS as $name => $meta) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'module' => $meta['module'],
                    'section' => $meta['section'],
                    'description' => str($meta['module'])->headline().' - '.str($meta['section'])->headline(),
                ]
            );
        }

        // updateOrCreate() writes straight to the database without
        // refreshing the registrar's in-memory permission cache, so the
        // rows created above are invisible to syncPermissions() below
        // until we clear it.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $everyPermission = Permission::query()->pluck('name')->all();

        Role::findOrCreate('Super Admin')->syncPermissions($everyPermission);
        Role::findOrCreate('Admin')->syncPermissions($this->adminPermissions);
    }
}
