<?php

namespace App\Repositories\Interfaces\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function latestForUser(User $user, int $limit): Collection;

    public function unreadCountForUser(User $user): int;

    public function markAsRead(User $user, string $notificationId): void;

    public function markAllAsRead(User $user): void;
}
