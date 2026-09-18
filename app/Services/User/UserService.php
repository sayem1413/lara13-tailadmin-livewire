<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Interfaces\User\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        string $sort = 'newest',
        array $filters = []
    ): LengthAwarePaginator {
        return $this->userRepository->paginate($search, $perPage, $sort, $filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data): User
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = $this->userRepository->create($data);

        $user->syncRoles($roles);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): User
    {
        // The Livewire form only disables the "Active" toggle client-side for
        // the signed-in user's own row, which doesn't stop a direct request
        // (or a tampered one) from deactivating the acting user's own
        // account and locking them out with no recovery path other than
        // another administrator.
        if ($user->id === Auth::id() && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user = $this->userRepository->update($user, $data);

        if ($roles !== null) {
            $user->syncRoles($roles);
        }

        return $user;
    }

    public function deleteUser(User $user): bool
    {
        return $this->userRepository->delete($user);
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkActivate(array $ids): int
    {
        return $this->userRepository->bulkActivate($this->authorizedIds($ids));
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function bulkDeactivate(array $ids): int
    {
        // Mirrors updateUser()'s self-deactivation guard: a bulk action
        // offers no per-row confirmation to catch the acting user
        // deactivating their own selected row.
        $ids = array_values(array_diff($ids, [Auth::id()]));

        return $this->userRepository->bulkDeactivate($this->authorizedIds($ids));
    }

    /**
     * Bulk actions bypass the per-row `@can` checks in the Blade table, so
     * each targeted user is re-checked against the same Policy a single
     * update goes through, silently dropping any the acting user isn't
     * allowed to touch (e.g. a Super Admin row for a non-Super-Admin actor).
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function authorizedIds(array $ids): array
    {
        return User::query()
            ->whereKey($ids)
            ->get()
            ->filter(fn (User $user) => Gate::allows('update', $user))
            ->pluck('id')
            ->all();
    }
}
