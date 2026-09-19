<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Repositories\Interfaces\Notification\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(
        protected NotificationRepositoryInterface $notificationRepository
    ) {}

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->notificationRepository->paginateForUser($user, $perPage);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function latestForUser(User $user, int $limit): Collection
    {
        return $this->notificationRepository->latestForUser($user, $limit);
    }

    public function unreadCountForUser(User $user): int
    {
        return $this->notificationRepository->unreadCountForUser($user);
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $this->notificationRepository->markAsRead($user, $notificationId);
    }

    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user);
    }
}
