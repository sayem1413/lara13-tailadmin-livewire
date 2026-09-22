<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Categories</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Organize your product catalog into categories.</p>
        </div>

        <div class="flex items-center gap-2">
            @can('admin.categories.export')
                <x-ui.button type="button" variant="secondary" wire:click="export">Export Excel</x-ui.button>
            @endcan

            @can('admin.categories.import')
                <x-import.button :model="\App\Models\Category::class" permission="admin.categories.import" title="Import Categories" wire:key="import-categories" />
            @endcan

            @can('create', \App\Models\Category::class)
                <a href="{{ route('admin.categories.create') }}">
                    <x-ui.button>Add Category</x-ui.button>
                </a>
            @endcan
        </div>
    </div>

    <x-ui.card :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-white/10">
            <div class="min-w-[220px] flex-1">
                <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search name or slug..." />
            </div>

            <div class="w-44">
                <x-forms.select wire:model.live="status">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="pending_activation">Pending Activation</option>
                    <option value="archived">Archived</option>
                </x-forms.select>
            </div>

            <div class="w-40">
                <x-forms.select wire:model.live="trashed">
                    <option value="">Active categories</option>
                    <option value="only">Trashed categories</option>
                </x-forms.select>
            </div>

            @if (! empty($selected) && $trashed === '')
                <div class="ml-auto flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($selected) }} selected</span>
                    <x-ui.button variant="secondary" wire:click="bulkActivate">Activate</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="bulkDeactivate">Deactivate</x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="bulkDelete"
                        data-confirm="delete"
                        data-confirm-entity="category"
                        data-confirm-name="the {{ count($selected) }} selected categories"
                    >Delete</x-ui.button>
                </div>
            @endif
        </div>

        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th class="w-10 px-4 py-3">
                        <x-forms.checkbox
                            wire:click="toggleSelectAllOnPage"
                            :checked="$this->allOnPageSelected($categories->pluck('id')->all())"
                        />
                    </th>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Parent</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Subcategories</th>
                    <th class="px-4 py-3 font-medium">Products</th>
                    <th class="px-4 py-3 font-medium">&nbsp;</th>
                </tr>
            </x-slot:head>

            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($categories as $category)
                    <tr wire:key="category-{{ $category->id }}">
                        <td class="px-4 py-3">
                            <x-forms.checkbox wire:model.live="selected" value="{{ $category->id }}" />
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800 dark:text-white/90">{{ $category->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $category->slug }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ $category->parent?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$this->statusColor($category)">{{ str($category->lifecycle_status->value)->headline() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $category->children_count }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $category->products_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3 text-sm">
                                @if ($trashed === 'only')
                                    @can('restore', $category)
                                        <button type="button" wire:click="restoreCategory({{ $category->id }})" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                            Restore
                                        </button>
                                    @endcan
                                    @can('forceDelete', $category)
                                        <button
                                            type="button"
                                            wire:click="forceDeleteCategory({{ $category->id }})"
                                            data-confirm="force-delete"
                                            data-confirm-entity="Category"
                                            data-confirm-name="{{ $category->name }}"
                                            class="font-medium text-red-600 hover:underline dark:text-red-400"
                                        >
                                            Force Delete
                                        </button>
                                    @endcan
                                @else
                                    @can('update', $category)
                                        <button type="button" wire:click="toggleActive({{ $category->id }})" class="font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            {{ $category->isLifecycleActive() ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    @endcan
                                    @can('delete', $category)
                                        <button
                                            type="button"
                                            wire:click="delete({{ $category->id }})"
                                            data-confirm="delete"
                                            data-confirm-entity="Category"
                                            data-confirm-name="{{ $category->name }}"
                                            class="font-medium text-red-600 hover:underline dark:text-red-400"
                                        >
                                            Delete
                                        </button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No categories found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="divide-y divide-gray-100 md:hidden dark:divide-white/10">
            @forelse ($categories as $category)
                <div wire:key="category-mobile-{{ $category->id }}" class="flex gap-3 p-4">
                    <x-forms.checkbox class="mt-1" wire:model.live="selected" value="{{ $category->id }}" />

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ $category->name }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $category->parent?->name ?? 'No parent' }}
                                </p>
                            </div>
                            <x-ui.badge :color="$this->statusColor($category)">{{ str($category->lifecycle_status->value)->headline() }}</x-ui.badge>
                        </div>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $category->children_count }} subcategories · {{ $category->products_count }} products
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                            @if ($trashed === 'only')
                                @can('restore', $category)
                                    <button type="button" wire:click="restoreCategory({{ $category->id }})" class="min-h-11 font-medium text-brand-600 dark:text-brand-400">
                                        Restore
                                    </button>
                                @endcan
                                @can('forceDelete', $category)
                                    <button
                                        type="button"
                                        wire:click="forceDeleteCategory({{ $category->id }})"
                                        data-confirm="force-delete"
                                        data-confirm-entity="Category"
                                        data-confirm-name="{{ $category->name }}"
                                        class="min-h-11 font-medium text-red-600 dark:text-red-400"
                                    >
                                        Force Delete
                                    </button>
                                @endcan
                            @else
                                @can('update', $category)
                                    <button type="button" wire:click="toggleActive({{ $category->id }})" class="min-h-11 font-medium text-gray-600 dark:text-gray-300">
                                        {{ $category->isLifecycleActive() ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="flex min-h-11 items-center font-medium text-brand-600 dark:text-brand-400">Edit</a>
                                @endcan
                                @can('delete', $category)
                                    <button
                                        type="button"
                                        wire:click="delete({{ $category->id }})"
                                        data-confirm="delete"
                                        data-confirm-entity="Category"
                                        data-confirm-name="{{ $category->name }}"
                                        class="min-h-11 font-medium text-red-600 dark:text-red-400"
                                    >
                                        Delete
                                    </button>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No categories found.</p>
            @endforelse
        </div>

        <x-ui.load-more :paginator="$categories" />
    </x-ui.card>
</div>
