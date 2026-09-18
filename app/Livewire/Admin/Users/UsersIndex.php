<?php

namespace App\Livewire\Admin\Users;

use App\Models\Permission\Role;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class UsersIndex extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = '';

    /** @var array<int, int> */
    public array $selected = [];

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->perPage = 10;
    }

    public function updatingRole(): void
    {
        $this->perPage = 10;
    }

    public function updatingStatus(): void
    {
        $this->perPage = 10;
    }

    public function loadMore(): void
    {
        $this->perPage += 10;
    }

    public function toggleActive(User $user): void
    {
        Gate::authorize('update', $user);

        app(UserService::class)->updateUser($user, ['is_active' => ! $user->is_active]);
    }

    public function delete(User $user): void
    {
        Gate::authorize('delete', $user);

        app(UserService::class)->deleteUser($user);

        $this->selected = array_values(array_diff($this->selected, [$user->id]));
    }

    public function toggleSelectAllOnPage(): void
    {
        $pageIds = $this->currentPageIds();

        $this->selected = $this->allOnPageSelected($pageIds)
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique(array_merge($this->selected, $pageIds)));
    }

    /**
     * @param  array<int, int>|null  $pageIds
     */
    public function allOnPageSelected(?array $pageIds = null): bool
    {
        $pageIds ??= $this->currentPageIds();

        return $pageIds !== [] && array_diff($pageIds, $this->selected) === [];
    }

    /**
     * @return array<int, int>
     */
    protected function currentPageIds(): array
    {
        return $this->users()->getCollection()->pluck('id')->all();
    }

    public function bulkActivate(): void
    {
        app(UserService::class)->bulkActivate($this->selected);

        $this->selected = [];
    }

    public function bulkDeactivate(): void
    {
        app(UserService::class)->bulkDeactivate($this->selected);

        $this->selected = [];
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function roleOptions(): Collection
    {
        return Role::query()->orderBy('name')->pluck('name');
    }

    public function render(): View
    {
        return view('livewire.admin.users.users-index', [
            'users' => $this->users(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    protected function users(): LengthAwarePaginator
    {
        return app(UserService::class)->paginate(
            search: $this->search,
            perPage: $this->perPage,
            // Reproduces the previous fixed `orderBy('name')` through the
            // shared applySort() helper rather than adding a new sort
            // control to the UI.
            sort: 'name_asc',
            filters: [
                'role' => $this->role,
                'is_active' => $this->status === '' ? null : $this->status === 'active',
            ],
        );
    }
}
