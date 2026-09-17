<?php

namespace App\Livewire\Admin\Roles;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        $role->syncPermissions($validated['selectedPermissions']);

        session()->flash('success', $this->roleId ? 'Role updated.' : 'Role created.');

        $this->redirect(route('admin.roles.index'));
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
     * Permissions grouped by their module prefix (the part before the
     * first dot, e.g. "users" for "users.view") for the checkbox matrix.
     *
     * @return Collection<int|string, EloquentCollection<int, Permission>>
     */
    protected function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->toString());
    }

    public function render(): View
    {
        return view('livewire.admin.roles.role-form', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }
}
