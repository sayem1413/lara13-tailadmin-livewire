<div class="relative" x-data="floatingMenu()" @click.outside="close()">
    <button x-ref="trigger" type="button" @click="toggle()" class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">
        <x-ui.icon name="bell" class="size-5" />
        @if ($this->unreadCount > 0)
            <span class="absolute top-1.5 right-1.5 flex size-2 rounded-full bg-red-500"></span>
        @endif
        <span class="sr-only">Notifications</span>
    </button>

    <div
        x-ref="panel"
        x-show="open"
        x-cloak
        x-transition
        class="z-40 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-800"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">Notifications</p>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                    Mark all as read
                </button>
            @endif
        </div>

        <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-white/10">
            @forelse ($this->notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-dropdown-{{ $notification->id }}"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="block w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/5 {{ $notification->read_at ? '' : 'bg-brand-50/50 dark:bg-brand-500/5' }}"
                >
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $notification->data['message'] ?? 'New notification' }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                </button>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
            @endforelse
        </div>

        <div class="border-t border-gray-100 px-4 py-2 text-center dark:border-white/10">
            <a href="{{ route('admin.notifications.index') }}" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                View all
            </a>
        </div>
    </div>
</div>
