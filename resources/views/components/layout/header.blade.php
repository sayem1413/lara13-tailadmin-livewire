<header class="relative sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-gray-200 bg-white px-4 sm:gap-4 sm:px-6 dark:border-white/10 dark:bg-gray-900" x-data="{ mobileSearchOpen: false }">
    <button
        type="button"
        @click="sidebarOpen = !sidebarOpen"
        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-white/5"
    >
        <x-ui.icon name="menu" />
        <span class="sr-only">Toggle sidebar</span>
    </button>

    <button
        type="button"
        @click="mobileSearchOpen = true"
        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 sm:hidden dark:text-gray-400 dark:hover:bg-white/5"
    >
        <x-ui.icon name="search" class="size-5" />
        <span class="sr-only">Search</span>
    </button>

    <div
        x-show="mobileSearchOpen"
        x-cloak
        x-transition
        @keydown.escape.window="mobileSearchOpen = false"
        class="absolute inset-x-0 top-0 z-10 flex h-16 items-center gap-2 bg-white px-4 sm:hidden dark:bg-gray-900"
    >
        <div class="min-w-0 flex-1">
            <livewire:layout.global-search />
        </div>
        <button type="button" @click="mobileSearchOpen = false" class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">
            <x-ui.icon name="x-mark" class="size-5" />
            <span class="sr-only">Close search</span>
        </button>
    </div>

    <div class="flex flex-1 items-center justify-end gap-3 sm:justify-between">
        <div class="hidden max-w-md flex-1 sm:block">
            <livewire:layout.global-search />
        </div>

        <div class="flex items-center gap-2" x-data="darkMode">
            <button
                type="button"
                @click="toggle"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
            >
                <x-ui.icon name="sun" class="size-5" x-show="!enabled" />
                <x-ui.icon name="moon" class="size-5" x-show="enabled" x-cloak />
                <span class="sr-only">Toggle dark mode</span>
            </button>

            <livewire:layout.notifications-dropdown />

            <div class="relative" x-data="floatingMenu()" @click.outside="close()">
                <button x-ref="trigger" type="button" @click="toggle()" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-gray-100 dark:hover:bg-white/5">
                    @if (auth()->user()->avatarUrl())
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="size-8 rounded-full object-cover" />
                    @else
                        <span class="flex size-8 items-center justify-center rounded-full bg-brand-50 text-sm font-medium text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            {{ auth()->user()->initials() }}
                        </span>
                    @endif
                    <span class="hidden text-sm font-medium text-gray-700 sm:block dark:text-gray-200">{{ auth()->user()->name }}</span>
                    <x-ui.icon name="chevron-down" class="hidden size-4 text-gray-400 sm:block" />
                </button>

                <div
                    x-ref="panel"
                    x-show="open"
                    x-cloak
                    x-transition
                    class="z-40 w-[min(14rem,calc(100vw-2rem))] rounded-xl border border-gray-200 bg-white py-2 shadow-lg dark:border-white/10 dark:bg-gray-800"
                >
                    <div class="border-b border-gray-100 px-4 py-2 dark:border-white/10">
                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
                    </div>

                    <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">
                        <x-ui.icon name="user-circle" class="size-4" />
                        Profile
                    </a>
                    <a href="{{ route('admin.profile.password') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">
                        <x-ui.icon name="lock" class="size-4" />
                        Change Password
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 pt-1 dark:border-white/10">
                        @csrf
                        <button
                            type="submit"
                            data-confirm="logout"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                        >
                            <x-ui.icon name="logout" class="size-4" />
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
