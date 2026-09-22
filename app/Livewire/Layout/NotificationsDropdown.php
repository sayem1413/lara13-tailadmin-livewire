<?php

namespace App\Livewire\Layout;

use App\Services\Notification\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationsDropdown extends Component
{
    public int $limit = 8;

    public function markAsRead(string $notificationId): void
    {
        $user = auth()->user();

        app(NotificationService::class)->markAsRead($user, $notificationId);
    }

    public function markAllAsRead(): void
    {
        $user = auth()->user();

        app(NotificationService::class)->markAllAsRead($user);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        $user = auth()->user();

        return app(NotificationService::class)->latestForUser($user, $this->limit);
    }

    #[Computed]
    public function unreadCount(): int
    {
        $user = auth()->user();

        return app(NotificationService::class)->unreadCountForUser($user);
    }

    public function render(): View
    {
        return view('livewire.layout.notifications-dropdown');
    }
}
