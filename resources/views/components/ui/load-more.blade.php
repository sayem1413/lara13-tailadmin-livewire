@props(['paginator'])

<div class="flex items-center justify-between border-t border-gray-100 p-4 dark:border-white/10">
    <span class="text-sm text-gray-500 dark:text-gray-400">
        Showing {{ $paginator->count() }} of {{ $paginator->total() }}
    </span>

    @if ($paginator->hasMorePages())
        <div x-data x-intersect="$wire.loadMore()" class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
            </svg>
            Loading more...
        </div>
    @endif
</div>
