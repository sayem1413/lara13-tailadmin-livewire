<?php

namespace Database\Seeders;

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Example roles for common scenarios, layered on top of the Super
     * Admin / Admin baseline that RolesAndPermissionsSeeder already
     * creates. Each entry is a job function built from the permissions
     * RolesAndPermissionsSeeder (via permissions:sync) has already
     * created: "manage" grants full CRUD (index/create/edit/destroy) on a
     * module, "view" grants read-only (.index) access, and "partial"
     * grants exact permission names for a module whose grantable actions
     * don't fit either shape - e.g. Settings, which has no create/destroy
     * of its own, just "edit" (view the page) and "update" (save it).
     *
     * As real business modules are added to this starter, add their own
     * roles here (or new ones) the same way - a role with no grant for a
     * module a real job function needs is unusable for that function.
     */
    protected const ROLES = [
        [
            'name' => 'User Manager',
            'description' => 'Manages user accounts without access to roles, permissions, or settings.',
            'manage' => ['users'],
            'view' => ['activity-log'],
        ],
        [
            'name' => 'Auditor',
            'description' => 'Read-only visibility into users, roles, and the activity log for compliance review.',
            'view' => ['users', 'roles', 'activity-log'],
        ],
        [
            'name' => 'Settings Manager',
            'description' => 'Views and updates application settings only.',
            'partial' => [
                'settings' => ['edit', 'update'],
            ],
        ],
    ];

    /**
     * The route-derived sections a full-CRUD module grants under "manage" -
     * matching what UserPolicy/RolePolicy actually check (see
     * app/Console/Commands/SyncPermissionsFromRoutes.php).
     */
    protected const MANAGE_ACTIONS = ['index', 'create', 'edit', 'destroy'];

    public function run(): void
    {
        foreach (self::ROLES as $definition) {
            $role = Role::updateOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                [
                    'description' => $definition['description'],
                    'is_active' => true,
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('name', $this->permissionNames($definition))
                ->pluck('id')
                ->all();

            $role->syncPermissions(Permission::expandWithImplied($permissionIds));
        }
    }

    /**
     * @param  array{manage?: array<int, string>, view?: array<int, string>, partial?: array<string, array<int, string>>}  $definition
     * @return array<int, string>
     */
    protected function permissionNames(array $definition): array
    {
        $names = [];

        foreach ($definition['manage'] ?? [] as $module) {
            foreach (self::MANAGE_ACTIONS as $action) {
                $names[] = "admin.{$module}.{$action}";
            }
        }

        foreach ($definition['view'] ?? [] as $module) {
            $names[] = "admin.{$module}.index";
        }

        foreach ($definition['partial'] ?? [] as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "admin.{$module}.{$action}";
            }
        }

        return $names;
    }
}
