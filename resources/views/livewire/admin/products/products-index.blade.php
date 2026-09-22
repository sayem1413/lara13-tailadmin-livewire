<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Products</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage your product catalog and pricing.</p>
        </div>

        <div class="flex items-center gap-2">
            @can('admin.products.export')
                <x-ui.button type="button" variant="secondary" wire:click="export">Export Excel</x-ui.button>
            @endcan

            @can('admin.products.import')
                <x-import.button :model="\App\Models\Product::class" permission="admin.products.import" title="Import Products" wire:key="import-products" />
            @endcan

            @can('create', \App\Models\Product::class)
                <a href="{{ route('admin.products.create') }}">
                    <x-ui.button>Add Product</x-ui.button>
                </a>
            @endcan
        </div>
    </div>

    <x-ui.card :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-white/10">
            <div class="min-w-[220px] flex-1">
                <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search name, SKU, or barcode..." />
            </div>

            <div class="w-52">
                <x-forms.select searchable wire:model.live="category">
                    <option value="">All categories</option>
                    @foreach ($this->categoryOptions as $option)
                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-forms.select>
            </div>

            @if ($category !== '')
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <x-forms.checkbox wire:model.live="includeSubcategories" />
                    Include subcategories
                </label>
            @endif

            <div class="w-40">
                <x-forms.select wire:model.live="stock">
                    <option value="">All stock</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                </x-forms.select>
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
                    <option value="">Active products</option>
                    <option value="only">Trashed products</option>
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
                        data-confirm-entity="product"
                        data-confirm-name="the {{ count($selected) }} selected products"
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
                            :checked="$this->allOnPageSelected($products->pluck('id')->all())"
                        />
                    </th>
                    <th class="px-4 py-3 font-medium">Product</th>
                    <th class="px-4 py-3 font-medium">Categories</th>
                    <th class="px-4 py-3 font-medium">Price</th>
                    <th class="px-4 py-3 font-medium">Stock</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">&nbsp;</th>
                </tr>
            </x-slot:head>

            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-4 py-3">
                            <x-forms.checkbox wire:model.live="selected" value="{{ $product->id }}" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($product->primaryImageUrl())
                                    <img src="{{ $product->primaryImageUrl() }}" alt="" class="size-10 shrink-0 rounded-lg object-cover" />
                                @else
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-white/5">
                                        <x-ui.icon name="box" class="size-5 text-gray-400" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ $product->categories->pluck('name')->implode(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ formatPrice((float) $product->price) }}</td>
                        <td class="px-4 py-3">
                            @if (! $product->track_inventory)
                                <span class="text-xs text-gray-400">Not tracked</span>
                            @elseif ($product->stock_quantity <= 0)
                                <x-ui.badge color="red">Out of stock</x-ui.badge>
                            @elseif ($product->isLowStock())
                                <x-ui.badge color="brand">{{ $product->stock_quantity }} left</x-ui.badge>
                            @else
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ $product->stock_quantity }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$this->statusColor($product)">{{ str($product->lifecycle_status->value)->headline() }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3 text-sm">
                                @if ($trashed === 'only')
                                    @can('restore', $product)
                                        <button type="button" wire:click="restoreProduct({{ $product->id }})" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                            Restore
                                        </button>
                                    @endcan
                                    @can('forceDelete', $product)
                                        <button
                                            type="button"
                                            wire:click="forceDeleteProduct({{ $product->id }})"
                                            data-confirm="force-delete"
                                            data-confirm-entity="Product"
                                            data-confirm-name="{{ $product->name }}"
                                            class="font-medium text-red-600 hover:underline dark:text-red-400"
                                        >
                                            Force Delete
                                        </button>
                                    @endcan
                                @else
                                    @can('update', $product)
                                        <button type="button" wire:click="toggleActive({{ $product->id }})" class="font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            {{ $product->isLifecycleActive() ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <a href="{{ route('admin.products.edit', $product) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                                    @endcan
                                    @can('delete', $product)
                                        <button
                                            type="button"
                                            wire:click="delete({{ $product->id }})"
                                            data-confirm="delete"
                                            data-confirm-entity="Product"
                                            data-confirm-name="{{ $product->name }}"
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
                            No products found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="divide-y divide-gray-100 md:hidden dark:divide-white/10">
            @forelse ($products as $product)
                <div wire:key="product-mobile-{{ $product->id }}" class="flex gap-3 p-4">
                    <x-forms.checkbox class="mt-1" wire:model.live="selected" value="{{ $product->id }}" />

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ $product->name }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }} · {{ formatPrice((float) $product->price) }}</p>
                            </div>
                            <x-ui.badge :color="$this->statusColor($product)">{{ str($product->lifecycle_status->value)->headline() }}</x-ui.badge>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                            @if ($trashed === 'only')
                                @can('restore', $product)
                                    <button type="button" wire:click="restoreProduct({{ $product->id }})" class="min-h-11 font-medium text-brand-600 dark:text-brand-400">
                                        Restore
                                    </button>
                                @endcan
                                @can('forceDelete', $product)
                                    <button
                                        type="button"
                                        wire:click="forceDeleteProduct({{ $product->id }})"
                                        data-confirm="force-delete"
                                        data-confirm-entity="Product"
                                        data-confirm-name="{{ $product->name }}"
                                        class="min-h-11 font-medium text-red-600 dark:text-red-400"
                                    >
                                        Force Delete
                                    </button>
                                @endcan
                            @else
                                @can('update', $product)
                                    <button type="button" wire:click="toggleActive({{ $product->id }})" class="min-h-11 font-medium text-gray-600 dark:text-gray-300">
                                        {{ $product->isLifecycleActive() ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="flex min-h-11 items-center font-medium text-brand-600 dark:text-brand-400">Edit</a>
                                @endcan
                                @can('delete', $product)
                                    <button
                                        type="button"
                                        wire:click="delete({{ $product->id }})"
                                        data-confirm="delete"
                                        data-confirm-entity="Product"
                                        data-confirm-name="{{ $product->name }}"
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
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No products found.</p>
            @endforelse
        </div>

        <x-ui.load-more :paginator="$products" />
    </x-ui.card>
</div>
