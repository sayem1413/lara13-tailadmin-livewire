<?php

namespace App\Services\User;

use App\Models\Permission\Role;
use App\Models\User;
use App\Notifications\UserRoleUpdatedNotification;
use App\Repositories\Interfaces\User\UserRepositoryInterface;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected NotificationService $notificationService
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  string|null  $trashed  'only' for soft-deleted rows only, 'with'
     *                                for both, anything else excludes them -
     *                                see applyTrashedFilter() in helpers.php.
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): LengthAwarePaginator {
        return $this->userRepository->paginate($search, $perPage, $sort, $filters, $trashed);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  string|null  $trashed  'only' for soft-deleted rows only, 'with'
     *                                for both, anything else excludes them -
     *                                see applyTrashedFilter() in helpers.php.
     * @return Builder<User>
     */
    public function filteredQuery(
        ?string $search = null,
        string $sort = 'newest',
        array $filters = [],
        ?string $trashed = null
    ): Builder {
        return $this->userRepository->filteredQuery($search, $sort, $filters, $trashed);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUser(array $data): User
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $this->guardAgainstUnassignableSuperAdminRole($roles);
        $this->guardAgainstInactiveRoleAssignment($roles);

        return DB::transaction(function () use ($data, $roles) {
            $user = $this->userRepository->create($data);

            $user->syncRoles($roles);

            return $user;
        });
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

        if ($roles !== null) {
            $this->guardAgainstUnassignableSuperAdminRole($roles);
            $this->guardAgainstInactiveRoleAssignment($roles);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return DB::transaction(function () use ($user, $data, $roles) {
            $previousRoles = $user->getRoleNames()->all();

            $user = $this->userRepository->update($user, $data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            if ($roles !== null && $this->rolesChanged($previousRoles, $roles)) {
                $this->notificationService->send($user, new UserRoleUpdatedNotification($user->getRoleNames()->all()));
            }

            return $user;
        });
    }

    /**
     * @param  array<int, string>  $before
     * @param  array<int, string>  $after
     */
    protected function rolesChanged(array $before, array $after): bool
    {
        return array_diff($before, $after) !== [] || array_diff($after, $before) !== [];
    }

    /**
     * The Users index and the Livewire form both build their role checkbox
     * list from assignableRoles()/UserForm::assignableRoles(), which never
     * offers "Super Admin" to a non-Super-Admin actor - but the resource
     * Controller's routes (StoreUserRequest/UpdateUserRequest) only check
     * that submitted role names exist, not who's allowed to grant them. A
     * tampered or direct API request could otherwise smuggle "Super Admin"
     * into $data['roles'] and self-promote.
     *
     * @param  array<int, string>  $roles
     */
    protected function guardAgainstUnassignableSuperAdminRole(array $roles): void
    {
        if (in_array('Super Admin', $roles, true) && ! auth()->user()?->hasRole('Super Admin')) {
            throw ValidationException::withMessages([
                'roles' => 'Only a Super Admin can assign the Super Admin role.',
            ]);
        }
    }

    /**
     * An inactive role represents a job function that's been retired but
     * deliberately not deleted (see RoleService::deleteRole()'s own "still
     * assigned to a user" guard, which exists precisely so a role already
     * in use can't just be removed out from under its holders) - so while
     * an inactive role is left alone for whoever already holds it, it must
     * never be handed out to a new sync. This is a plain boolean check, not
     * a Lifecycle Integrity guard: is_active on Role has no cascade/orphan
     * behavior, it just blocks this one write.
     *
     * @param  array<int, string>  $roles
     */
    protected function guardAgainstInactiveRoleAssignment(array $roles): void
    {
        $inactiveRoles = Role::query()
            ->whereIn('name', $roles)
            ->where('is_active', false)
            ->pluck('name');

        if ($inactiveRoles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => "The following role(s) are inactive and cannot be assigned: {$inactiveRoles->implode(', ')}.",
            ]);
        }
    }

    public function findOrFail(int $id, bool $withTrashed = false): User
    {
        return $this->userRepository->findOrFail($id, $withTrashed);
    }

    public function deleteUser(User $user): bool
    {
        return $this->userRepository->delete($user);
    }

    public function restoreUser(User $user): User
    {
        return $this->userRepository->restore($user);
    }

    /**
     * Permanently removes the user row. The user's avatar media is cleaned
     * up automatically - InteractsWithMedia hooks the model's "deleting"
     * event and calls deleteAllMedia() once forceDeleting is true, so no
     * separate media cleanup is needed here.
     */
    public function forceDeleteUser(User $user): bool
    {
        return $this->userRepository->forceDelete($user);
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
