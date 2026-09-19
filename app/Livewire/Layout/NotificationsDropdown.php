<?php

namespace App\Livewire\Layout;

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
        auth()->user()->notifications()->whereKey($notificationId)->first()?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        // ->unreadNotifications (a property) loads the whole collection and
        // marks each one read with its own UPDATE query; the relation
        // method instead issues a single UPDATE covering every row.
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        return auth()->user()->notifications()->latest()->limit($this->limit)->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function render(): View
    {
        return view('livewire.layout.notifications-dropdown');
    }
}
