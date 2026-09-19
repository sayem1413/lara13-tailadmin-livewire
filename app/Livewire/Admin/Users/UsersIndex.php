<?php

namespace App\Livewire\Admin\Users;

use App\Models\Permission\Role;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        // The row's toggle button is hidden for the signed-in user's own
        // row, but that's only a client-side guard - a tampered/direct
        // `wire:click` call can still reach here, so the resulting
        // ValidationException (see UserService::updateUser) needs its own
        // feedback rather than failing with no visible effect.
        try {
            app(UserService::class)->updateUser($user, ['is_active' => ! $user->is_active]);
        } catch (ValidationException $exception) {
            $this->dispatch('toast', type: 'error', message: collect($exception->errors())->flatten()->first());
        }
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
        $affected = app(UserService::class)->bulkActivate($this->selected);

        $this->reportBulkResult('Activated', $affected);

        $this->selected = [];
    }

    public function bulkDeactivate(): void
    {
        $affected = app(UserService::class)->bulkDeactivate($this->selected);

        $this->reportBulkResult('Deactivated', $affected);

        $this->selected = [];
    }

    /**
     * UserService::bulkActivate()/bulkDeactivate() silently drop any
     * selected id the actor isn't authorized to touch (e.g. a Super Admin
     * row, or - for deactivate - the actor's own row), so without this the
     * selection just clears with no indication only part of it changed.
     */
    protected function reportBulkResult(string $verb, int $affected): void
    {
        $skipped = count($this->selected) - $affected;

        $this->dispatch('toast', type: $skipped > 0 ? 'warning' : 'success', message: $skipped > 0
            ? "{$verb} {$affected} user(s); {$skipped} skipped (insufficient permission)."
            : "{$verb} {$affected} user(s).");
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
