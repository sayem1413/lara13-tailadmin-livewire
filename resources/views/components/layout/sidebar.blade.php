@props(['menu' => []])

<div
    x-show="sidebarOpen"
    x-cloak
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
></div>

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 flex-col border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:static lg:translate-x-0 dark:border-white/10 dark:bg-gray-900"
>
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-200 px-6 dark:border-white/10">
        <a href="{{ route('admin.dashboard.index') }}" class="flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-white/90">
            <span class="flex size-8 items-center justify-center rounded-lg bg-brand-500 text-sm font-bold text-white">
                {{ Str::substr(setting('app_name', config('app.name')), 0, 1) }}
            </span>
            {{ setting('app_name', config('app.name')) }}
        </a>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-6">
        @foreach ($menu as $group)
            <div>
                <p class="mb-2 px-2 text-xs font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    {{ $group['group'] }}
                </p>

                <ul class="space-y-1">
                    @foreach ($group['items'] as $item)
                        <li>
                            @if (! empty($item['children']))
                                @php
                                    $childActive = collect($item['children'])->contains(fn ($child) => request()->routeIs($child['route']));
                                @endphp
                                <div x-data="{ open: {{ $childActive ? 'true' : 'false' }} }">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"
                                    >
                                        <x-ui.icon :name="$item['icon'] ?? 'grid'" class="size-5 shrink-0" />
                                        <span class="flex-1 text-left">{{ $item['label'] }}</span>
                                        <x-ui.icon name="chevron-down" class="size-4 shrink-0 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                                    </button>

                                    <ul x-show="open" x-cloak x-transition class="mt-1 space-y-1 border-l border-gray-200 pl-6 dark:border-white/10">
                                        @foreach ($item['children'] as $child)
                                            <li>
                                                <a
                                                    href="{{ route($child['route']) }}"
                                                    class="block rounded-lg px-3 py-2 text-sm {{ request()->routeIs($child['route']) ? 'font-medium text-brand-600 dark:text-brand-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' }}"
                                                >
                                                    {{ $child['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <a
                                    href="{{ route($item['route']) }}"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs($item['route']) ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5' }}"
                                >
                                    <x-ui.icon :name="$item['icon'] ?? 'grid'" class="size-5 shrink-0" />
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
