<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UsersIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = '';

    /** @var array<int, int> */
    public array $selected = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function toggleActive(User $user): void
    {
        Gate::authorize('update', $user);

        $user->update(['is_active' => ! $user->is_active]);
    }

    public function delete(User $user): void
    {
        Gate::authorize('delete', $user);

        $user->delete();

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
        $this->bulkSetActive(true);
    }

    public function bulkDeactivate(): void
    {
        $this->bulkSetActive(false);
    }

    protected function bulkSetActive(bool $active): void
    {
        User::query()
            ->whereKey($this->selected)
            ->get()
            ->each(function (User $user) use ($active) {
                if (Gate::allows('update', $user)) {
                    $user->update(['is_active' => $active]);
                }
            });

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
        return User::query()
            ->with('roles')
            ->when($this->search, fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when($this->role, fn ($query) => $query->whereHas(
                'roles', fn ($q) => $q->where('name', $this->role)
            ))
            ->when($this->status !== '', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->orderBy('name')
            ->paginate(10);
    }
}
