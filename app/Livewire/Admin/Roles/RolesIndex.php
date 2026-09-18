<?php

namespace App\Livewire\Admin\Roles;

use App\Models\Permission\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RolesIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $error = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(Role $role): void
    {
        $this->error = null;

        // Bypasses Gate::before too: a Super Admin actor must not be able
        // to delete the one role every Gate::before check depends on.
        if ($role->name === 'Super Admin') {
            $this->error = 'The Super Admin role cannot be deleted.';

            return;
        }

        Gate::authorize('delete', $role);

        if ($role->users()->exists()) {
            $this->error = "\"{$role->name}\" is assigned to at least one user and can't be deleted.";

            return;
        }

        $role->delete();
    }

    public function render(): View
    {
        return view('livewire.admin.roles.roles-index', [
            'roles' => $this->roles(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    protected function roles(): LengthAwarePaginator
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);
    }
}
