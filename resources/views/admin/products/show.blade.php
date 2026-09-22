<x-app-layout title="View Product">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $product->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $product->sku }}</p>
        </div>

        @can('update', $product)
            <a href="{{ route('admin.products.edit', $product) }}">
                <x-ui.button>Edit Product</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.card title="Details">
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Status</dt>
                <dd class="mt-1">
                    <x-ui.badge :color="match ($product->lifecycle_status->value) {
                        'active' => 'green',
                        'pending_activation' => 'brand',
                        'archived' => 'red',
                        default => 'gray',
                    }">{{ str($product->lifecycle_status->value)->headline() }}</x-ui.badge>
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Price</dt>
                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ formatPrice((float) $product->price) }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Stock</dt>
                <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ $product->track_inventory ? $product->stock_quantity : 'Not tracked' }}
                </dd>
            </div>

            <div class="sm:col-span-3">
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Categories</dt>
                <dd class="mt-1 flex flex-wrap gap-1">
                    @forelse ($product->categories as $category)
                        <x-ui.badge color="gray">{{ $category->name }}</x-ui.badge>
                    @empty
                        <span class="text-sm text-gray-500 dark:text-gray-400">Uncategorized.</span>
                    @endforelse
                </dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Recent Stock Movements" class="mt-6">
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @forelse ($stockMovements as $movement)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ str($movement->type->value)->headline() }}{{ $movement->reason ? " — {$movement->reason}" : '' }}</span>
                    <span class="font-medium {{ $movement->quantity_change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $movement->quantity_change >= 0 ? '+' : '' }}{{ $movement->quantity_change }}
                    </span>
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No stock movements yet.</p>
            @endforelse
        </div>
    </x-ui.card>

    <div class="mt-4">
        <a href="{{ route('admin.products.index') }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">← Back to Products</a>
    </div>
</x-app-layout>
