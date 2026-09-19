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
     * Permissions for actions that have no dedicated route - see
     * app/Console/Commands/SyncPermissionsFromRoutes.php, which discovers
     * permissions from named "admin.*" routes and has no way to see a
     * Livewire component method such as an index page's delete button or a
     * settings form's save action. Named in the same admin.<module>.
     * <section> shape the sync command produces, using its resource-style
     * section vocabulary, so Permission::IMPLYING_SECTIONS/IMPLIED_SECTIONS
     * treats these the same as a route-backed permission.
     *
     * @var array<string, array{module: string, section: string}>
     */
    protected array $manualPermissions = [
        'admin.notifications.preferences.edit' => ['module' => 'notifications', 'section' => 'preferences.edit'],
        'admin.users.import' => ['module' => 'users', 'section' => 'import'],
        'admin.users.export' => ['module' => 'users', 'section' => 'export'],
    ];

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

        foreach ($this->manualPermissions as $name => $meta) {
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
