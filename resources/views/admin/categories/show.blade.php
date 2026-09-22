<x-app-layout title="View Category">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $category->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $category->parent?->name ? "Under {$category->parent->name}" : 'Top-level category' }}</p>
        </div>

        @can('update', $category)
            <a href="{{ route('admin.categories.edit', $category) }}">
                <x-ui.button>Edit Category</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.card title="Details">
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Status</dt>
                <dd class="mt-1">
                    <x-ui.badge :color="match ($category->lifecycle_status->value) {
                        'active' => 'green',
                        'pending_activation' => 'brand',
                        'archived' => 'red',
                        default => 'gray',
                    }">{{ str($category->lifecycle_status->value)->headline() }}</x-ui.badge>
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Slug</dt>
                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $category->slug }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Description</dt>
                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $category->description ?: 'No description.' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Subcategories" class="mt-6">
        <div class="flex flex-wrap gap-1">
            @forelse ($category->children as $child)
                <x-ui.badge color="gray">{{ $child->name }}</x-ui.badge>
            @empty
                <span class="text-sm text-gray-500 dark:text-gray-400">No subcategories.</span>
            @endforelse
        </div>
    </x-ui.card>

    <div class="mt-4">
        <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Back to Categories</a>
    </div>
</x-app-layout>
