<?php

namespace App\Livewire\Admin\Notifications;

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
        auth()->user()->notifications()->whereKey($notificationId)->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
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
        return auth()->user()
            ->notifications()
            ->latest()
            ->paginate($this->perPage, page: 1);
    }
}
