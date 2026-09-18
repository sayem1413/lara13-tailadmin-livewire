<?php

namespace App\Livewire\Admin\Roles;

use App\Models\Permission\Role;
use App\Services\Role\RoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class RolesIndex extends Component
{
    #[Url]
    public string $search = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
    }

    public function delete(Role $role): void
    {
        Gate::authorize('delete', $role);

        try {
            app(RoleService::class)->deleteRole($role);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());

            return;
        }

        $this->dispatch('toast', type: 'success', message: "\"{$role->name}\" deleted.");
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
        return app(RoleService::class)->paginate(
            search: $this->search,
            perPage: $this->perPage,
            sort: 'name_asc',
        );
    }
}
