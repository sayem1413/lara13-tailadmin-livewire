<?php

namespace App\Repositories\Eloquent\Notification;

use App\Models\User;
use App\Repositories\Interfaces\Notification\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationRepository implements NotificationRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate($perPage, page: 1);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function latestForUser(User $user, int $limit): Collection
    {
        return $user->notifications()->latest()->limit($limit)->get();
    }

    public function unreadCountForUser(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $user->notifications()->whereKey($notificationId)->update(['read_at' => now()]);
    }

    public function markAllAsRead(User $user): void
    {
        // The relation-builder call issues a single UPDATE covering every
        // unread row, unlike accessing ->unreadNotifications as a property
        // (which loads the whole collection and issues one UPDATE per row).
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
