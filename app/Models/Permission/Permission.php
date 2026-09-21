<?php

namespace App\Models\Permission;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string|null $module
 * @property string|null $section
 * @property string|null $description
 */
#[Fillable(['name', 'guard_name', 'module', 'section', 'description'])]
class Permission extends SpatiePermission
{
    use LogsActivity;

    /**
     * Sections that grant write/manage access to a module. Granting any of
     * these on a module implies the module's view sections (see
     * IMPLIED_SECTIONS) must also be granted — a user who can create, edit,
     * or delete a record needs to be able to see it too.
     */
    public const IMPLYING_SECTIONS = ['create', 'store', 'edit', 'update', 'destroy'];

    /**
     * The "view" sections implied by IMPLYING_SECTIONS. Only sections that
     * actually exist for a given module (its routes were registered with
     * them) are ever granted, so this works unchanged for both full
     * resources (index + show) and shallow nested resources (index only).
     */
    public const IMPLIED_SECTIONS = ['index', 'show'];

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'guard_name' => 'string',
            'module' => 'string',
            'section' => 'string',
            'description' => 'string',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'guard_name', 'module', 'section', 'description'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * The permission IDs that must be granted alongside the given selection
     * because one of its permissions implies module-level view access.
     *
     * @param  array<int, int>  $selectedIds
     * @return Collection<int, int>
     */
    public static function impliedIdsFor(array $selectedIds): Collection
    {
        if ($selectedIds === []) {
            return collect();
        }

        $selected = static::query()
            ->whereIn('id', $selectedIds)
            ->get(['id', 'module', 'section']);

        $modulesNeedingView = $selected
            ->filter(fn (self $permission) => in_array($permission->section, self::IMPLYING_SECTIONS, true))
            ->pluck('module')
            ->filter()
            ->unique();

        if ($modulesNeedingView->isEmpty()) {
            return collect();
        }

        return static::query()
            ->whereIn('module', $modulesNeedingView)
            ->whereIn('section', self::IMPLIED_SECTIONS)
            ->pluck('id');
    }

    /**
     * The given permission IDs plus any module-level view permissions they
     * imply. Purely additive — never drops a selected permission.
     *
     * @param  array<int, int>  $selectedIds
     * @return array<int, int>
     */
    public static function expandWithImplied(array $selectedIds): array
    {
        return collect($selectedIds)
            ->merge(self::impliedIdsFor($selectedIds))
            ->unique()
            ->values()
            ->all();
    }
}
