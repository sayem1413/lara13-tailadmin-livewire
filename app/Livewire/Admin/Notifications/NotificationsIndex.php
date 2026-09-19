<?php

namespace App\Livewire\Admin\Notifications;

use App\Services\Notification\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;

class NotificationsIndex extends Component
{
    public int $perPage = 20;

    public function loadMore(): void
    {
        $this->perPage += 20;
    }

    public function markAsRead(string $notificationId): void
    {
        app(NotificationService::class)->markAsRead(auth()->user(), $notificationId);
    }

    public function markAllAsRead(): void
    {
        app(NotificationService::class)->markAllAsRead(auth()->user());
    }

    public function render(): View
    {
        return view('livewire.admin.notifications.notifications-index', [
            'notifications' => $this->notifications(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    protected function notifications(): LengthAwarePaginator
    {
        return app(NotificationService::class)->paginateForUser(auth()->user(), $this->perPage);
    }
}
