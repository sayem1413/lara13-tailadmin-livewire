<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <div class="relative">
        <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
        <input
            type="search"
            wire:model.live.debounce.300ms="query"
            @focus="open = true"
            placeholder="Search..."
            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pr-4 pl-10 text-sm text-gray-700 placeholder:text-gray-400 focus:border-brand-500 focus:bg-white focus:ring-3 focus:ring-brand-500/10 focus:outline-none dark:border-white/10 dark:bg-white/5 dark:text-white/90 dark:focus:bg-white/5"
        />
    </div>

    @if (mb_strlen(trim($query)) >= 2)
        <div x-show="open" x-cloak class="absolute z-40 mt-2 w-full rounded-xl border border-gray-200 bg-white py-2 shadow-lg dark:border-white/10 dark:bg-gray-800">
            @forelse ($this->results as $result)
                <a href="{{ $result['url'] }}" class="block px-4 py-2 hover:bg-gray-50 dark:hover:bg-white/5">
                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $result['title'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $result['module'] }}@if ($result['description']) &middot; {{ $result['description'] }}@endif
                    </p>
                </a>
            @empty
                <p class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No results found.</p>
            @endforelse
        </div>
    @endif
</div>
