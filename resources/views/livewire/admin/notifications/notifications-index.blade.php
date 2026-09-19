<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Notifications</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Everything you've been notified about.</p>
        </div>

        @if ($notifications->contains(fn ($notification) => $notification->read_at === null))
            <button type="button" wire:click="markAllAsRead" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">
                Mark all as read
            </button>
        @endif
    </div>

    <x-ui.card :padded="false">
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @forelse ($notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="flex w-full items-start gap-3 px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-white/5 {{ $notification->read_at ? '' : 'bg-brand-50/50 dark:bg-brand-500/5' }}"
                >
                    <x-ui.icon name="bell" class="mt-0.5 size-5 shrink-0 text-gray-400" />
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $notification->data['message'] ?? 'New notification' }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </button>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
            @endforelse
        </div>

        <x-ui.load-more :paginator="$notifications" />
    </x-ui.card>
</div>
