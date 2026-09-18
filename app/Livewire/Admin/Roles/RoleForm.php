<?php

namespace App\Livewire\Admin\Roles;

use App\Models\Permission\Permission;
use App\Models\Permission\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RoleForm extends Component
{
    #[Locked]
    public ?int $roleId = null;

    public string $name = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->guardAgainstSuperAdminRole($role);
            Gate::authorize('update', $role);

            $this->roleId = (int) $role->id;
            $this->name = $role->name;
            $this->selectedPermissions = $role->permissions->pluck('name')->all();
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
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function save(): void
    {
        $role = $this->roleId ? Role::findOrFail($this->roleId) : new Role(['guard_name' => 'web']);

        if ($this->roleId) {
            $this->guardAgainstSuperAdminRole($role);
        }

        Gate::authorize($this->roleId ? 'update' : 'create', $this->roleId ? $role : Role::class);

        $validated = $this->validate();

        $role->name = $validated['name'];
        $role->save();
        $role->syncPermissions($this->expandSelectedPermissions($validated['selectedPermissions']));

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
        $this->selectedPermissions = $this->expandSelectedPermissions($this->selectedPermissions);
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

        $this->selectedPermissions = $this->expandSelectedPermissions($this->selectedPermissions);
    }

    /**
     * Checks every permission across every module, or unchecks all of them
     * if everything is already checked.
     */
    public function toggleAllPermissions(): void
    {
        $allNames = Permission::query()->pluck('name')->all();

        $allSelected = empty(array_diff($allNames, $this->selectedPermissions));

        $this->selectedPermissions = $allSelected ? [] : $allNames;
    }

    /**
     * The given permission names, plus any module-level view permissions
     * they imply. Re-applied on save (not just on the live update above) so
     * a direct/tampered request can't submit a write permission without its
     * implied view permission.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    protected function expandSelectedPermissions(array $names): array
    {
        if ($names === []) {
            return $names;
        }

        $ids = Permission::query()->whereIn('name', $names)->pluck('id')->all();

        return Permission::query()
            ->whereIn('id', Permission::expandWithImplied($ids))
            ->pluck('name')
            ->all();
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
     * Permissions grouped by module (e.g. "users") for the checkbox matrix.
     *
     * @return Collection<int|string, EloquentCollection<int, Permission>>
     */
    protected function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('section')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->module ?? $permission->name);
    }

    public function render(): View
    {
        return view('livewire.admin.roles.role-form', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }
}
