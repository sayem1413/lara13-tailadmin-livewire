<?php

namespace App\Livewire\Admin\Roles;

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use App\Services\Permission\PermissionService;
use App\Services\Role\RoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RoleForm extends Component
{
    #[Locked]
    public ?int $roleId = null;

    public string $name = '';

    public ?string $description = null;

    public bool $is_active = true;

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    /**
     * A snapshot of $selectedPermissions as loaded, so the view can warn
     * before saving a change that revokes access rather than just grants
     * it - a role losing a permission is a more consequential action than
     * gaining one. Never mutated after mount().
     *
     * @var array<int, string>
     */
    public array $originalPermissions = [];

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->guardAgainstSuperAdminRole($role);
            Gate::authorize('update', $role);

            $this->roleId = (int) $role->id;
            $this->name = $role->name;
            $this->description = $role->description;
            $this->is_active = $role->is_active;
            $this->selectedPermissions = $role->permissions->pluck('name')->all();
            $this->originalPermissions = $this->selectedPermissions;
        } else {
            Gate::authorize('create', Role::class);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->roleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function save(): void
    {
        $role = $this->roleId ? app(RoleService::class)->findOrFail($this->roleId) : null;

        Gate::authorize($this->roleId ? 'update' : 'create', $role ?? Role::class);

        $validated = $this->validate();

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'is_active' => $validated['is_active'],
            'permissions' => $validated['selectedPermissions'],
        ];

        if ($role) {
            app(RoleService::class)->updateRole($role, $data);
        } else {
            app(RoleService::class)->createRole($data);
        }

        session()->flash('success', $this->roleId ? 'Role updated.' : 'Role created.');

        $this->redirect(route('admin.roles.index'));
    }

    /**
     * Auto-check a module's implied view permissions as soon as one of its
     * write permissions is checked, so the matrix visibly reflects that a
     * role able to create, edit, or delete a record must also be able to
     * see it (see Permission::IMPLYING_SECTIONS).
     */
    public function updatedSelectedPermissions(): void
    {
        $this->selectedPermissions = app(RoleService::class)->expandPermissions($this->selectedPermissions);
    }

    /**
     * Checks every permission in the given module if any of them aren't
     * already selected, otherwise unchecks the whole module.
     *
     * @param  array<int, string>  $namesInGroup
     */
    public function toggleGroup(array $namesInGroup): void
    {
        $allSelected = empty(array_diff($namesInGroup, $this->selectedPermissions));

        $this->selectedPermissions = $allSelected
            ? array_values(array_diff($this->selectedPermissions, $namesInGroup))
            : array_values(array_unique([...$this->selectedPermissions, ...$namesInGroup]));

        $this->selectedPermissions = app(RoleService::class)->expandPermissions($this->selectedPermissions);
    }

    /**
     * Checks every permission across every module, or unchecks all of them
     * if everything is already checked.
     */
    public function toggleAllPermissions(): void
    {
        $allNames = app(PermissionService::class)->allNames();

        $allSelected = empty(array_diff($allNames, $this->selectedPermissions));

        $this->selectedPermissions = $allSelected ? [] : $allNames;
    }

    /**
     * The Super Admin role bypasses all authorization via Gate::before, so
     * even a Super Admin actor is blocked here - otherwise they could
     * rename or strip permissions from the one role every Gate::before
     * check depends on, locking every Super Admin out at once.
     */
    protected function guardAgainstSuperAdminRole(Role $role): void
    {
        abort_if($role->name === 'Super Admin', 403, 'The Super Admin role cannot be edited.');
    }

    /**
     * Permissions grouped by module (e.g. "users") for the module cards.
     *
     * @return Collection<int|string, EloquentCollection<int, Permission>>
     */
    protected function permissionGroups(): Collection
    {
        return app(PermissionService::class)->groupedByModule();
    }

    /**
     * Permission names the signed-in user currently holds themselves -
     * read-only, purely for the view to grey out (not omit) a checkbox
     * the escalation guard would reject anyway, so an admin sees WHY a
     * box is unavailable rather than assuming it's missing by mistake.
     * Mirrors RoleService::guardAgainstUnassignablePermissions()'s own
     * check exactly (Super Admin's role is itself synced to hold every
     * permission - see RolesAndPermissionsSeeder - so this needs no
     * separate Super Admin branch).
     *
     * @return array<int, string>
     */
    protected function heldPermissionNames(): array
    {
        return auth()->user()?->getAllPermissions()->pluck('name')->all() ?? [];
    }

    /**
     * An icon name (see x-ui.icon) for a permission group's module -
     * purely decorative. Falls back to x-ui.icon's own default ('grid')
     * for any module not explicitly mapped here, so a newly seeded
     * module still renders a reasonable card without needing this list
     * updated first.
     */
    public function moduleIcon(string $module): string
    {
        return match ($module) {
            'users' => 'users',
            'roles' => 'shield',
            'settings' => 'settings',
            'notifications' => 'bell',
            'activity-log' => 'clock',
            'media' => 'upload-cloud',
            default => 'grid',
        };
    }

    /**
     * A short, friendly label for a permission's route-derived `section`
     * (e.g. 'index' -> 'View', 'destroy' -> 'Delete') - purely a display
     * transform, the underlying section/permission name is unchanged.
     * Falls back to a headline-cased version of the raw section for
     * anything not explicitly mapped, so a new route action still renders
     * reasonably without needing this list updated first.
     */
    public function sectionLabel(?string $section): string
    {
        return match ($section) {
            'index' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'update' => 'Update',
            'destroy' => 'Delete',
            'export' => 'Export',
            'import' => 'Import',
            default => Str::headline((string) $section),
        };
    }

    public function render(): View
    {
        return view('livewire.admin.roles.role-form', [
            'permissionGroups' => $this->permissionGroups(),
            'heldPermissions' => $this->heldPermissionNames(),
        ]);
    }
}
