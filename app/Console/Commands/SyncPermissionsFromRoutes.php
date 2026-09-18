<?php

namespace App\Console\Commands;

use App\Models\Permission\Permission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

#[Signature('permissions:sync')]
#[Description('Sync Spatie permissions with route names cleanly')]
class SyncPermissionsFromRoutes extends Command
{
    /**
     * Route name patterns (fnmatch-style) to skip - for routes that exist
     * but shouldn't become their own grantable permission. The dashboard,
     * notifications, and profile pages have no permission of their own by
     * design - every active user can reach them regardless of role - so a
     * permission for them would sit in the Role editor without controlling
     * anything. Add further patterns as similar situations come up, e.g.
     * an AJAX search endpoint meant to be gated by the same permission as
     * the page it searches, or an export action gated by its module's
     * "index" permission rather than one of its own.
     *
     * @var array<int, string>
     */
    protected array $ignoredRoutes = [
        'admin.dashboard.*',
        'admin.notifications.*',
        'admin.profile.*',
        // Users'/Roles' resource controllers expose store/show/update as
        // their own named routes, but the Policy gates them on the same
        // permission as create/index/edit respectively - a separate
        // permission for each would just be an orphan toggle in the Role
        // editor that controls nothing.
        'admin.users.store',
        'admin.users.show',
        'admin.users.update',
        'admin.roles.store',
        'admin.roles.show',
        'admin.roles.update',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /** @var RouteCollection $routes */
        $routes = Route::getRoutes();
        $validPermissionNames = [];

        foreach ($routes as $route) {
            $name = $route->getName();

            // Match only named admin routes
            if ($name && str_starts_with($name, 'admin.')) {

                // Skip explicitly ignored routes
                if ($this->isIgnored($name)) {
                    continue;
                }

                [$module, $section] = $this->moduleAndSection($name);

                Permission::updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'module' => $module,
                        'section' => $section,
                        'description' => $this->describe($module, $section),
                    ]
                );

                $validPermissionNames[] = $name;
            }
        }

        // Clean up permissions in DB whose routes no longer exist
        $deletedCount = Permission::whereNotIn('name', $validPermissionNames)->delete();

        // updateOrCreate() and delete() both write straight to the database
        // without refreshing the registrar's in-memory/cached permission
        // list, so anything that syncs role permissions right after this -
        // in this request or a later one, within the cache's TTL - would
        // otherwise miss what just changed.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Synced '.count($validPermissionNames).' active permissions.');
        if ($deletedCount > 0) {
            $this->warn("Removed {$deletedCount} stale permissions from DB.");
        }
    }

    private function isIgnored(string $routeName): bool
    {
        foreach ($this->ignoredRoutes as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derive a "module" (resource) and "section" (action) grouping from a
     * route name like "admin.branches.show" -> ['branches', 'show'].
     *
     * @return array{0: string, 1: string}
     */
    private function moduleAndSection(string $routeName): array
    {
        $segments = explode('.', Str::after($routeName, 'admin.'));

        $section = array_pop($segments) ?: 'index';
        $module = implode('.', $segments) ?: $section;

        return [$module, $section];
    }

    private function describe(string $module, string $section): string
    {
        $moduleLabel = Str::headline(str_replace('.', ' ', $module));
        $sectionLabel = Str::headline($section);

        return "{$moduleLabel} - {$sectionLabel}";
    }
}
