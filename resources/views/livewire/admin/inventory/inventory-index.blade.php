<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Inventory</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Track and adjust stock levels for tracked products.</p>
        </div>

        @can('admin.inventory.export')
            <x-ui.button type="button" variant="secondary" wire:click="export">Export Excel</x-ui.button>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card :padded="false" class="lg:col-span-2">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-white/10">
                <div class="min-w-[220px] flex-1">
                    <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search name or SKU..." />
                </div>

                <div class="w-44">
                    <x-forms.select wire:model.live="stock">
                        <option value="">All tracked stock</option>
                        <option value="low">Low stock</option>
                        <option value="out">Out of stock</option>
                    </x-forms.select>
                </div>
            </div>

            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 font-medium">Product</th>
                        <th class="px-4 py-3 font-medium">On Hand</th>
                        <th class="px-4 py-3 font-medium">Threshold</th>
                        <th class="px-4 py-3 font-medium">&nbsp;</th>
                    </tr>
                </x-slot:head>

                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($products as $product)
                        <tr wire:key="inventory-{{ $product->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-white/90">{{ $product->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $product->sku }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @if ($product->stock_quantity <= 0)
                                    <x-ui.badge color="red">{{ $product->stock_quantity }}</x-ui.badge>
                                @elseif ($product->isLowStock())
                                    <x-ui.badge color="brand">{{ $product->stock_quantity }}</x-ui.badge>
                                @else
                                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ $product->stock_quantity }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                {{ $product->low_stock_threshold ?? setting('low_stock_threshold', 10) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('adjustStock', $product)
                                    <button type="button" wire:click="openAdjustModal({{ $product->id }})" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                        Adjust Stock
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No tracked products found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>

            <x-ui.load-more :paginator="$products" />
        </x-ui.card>

        <x-ui.card title="Recent Movements">
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($recentMovements as $movement)
                    <div class="py-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $movement->product?->name ?? 'Deleted product' }}</span>
                            <span class="font-medium {{ $movement->quantity_change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $movement->quantity_change >= 0 ? '+' : '' }}{{ $movement->quantity_change }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ str($movement->type->value)->headline() }} · {{ $movement->created_at?->diffForHumans() }}
                        </p>
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No stock movements yet.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    <x-ui.modal wire-model="showAdjustModal" title="Adjust Stock — {{ $adjustingProductName }}">
        <form wire:submit="adjust" class="space-y-4">
            <div>
                <x-forms.label for="type">Movement Type</x-forms.label>
                <x-forms.select wire:model="type" id="type">
                    @foreach (\App\Enums\StockMovementType::cases() as $case)
                        <option value="{{ $case->value }}">{{ str($case->name)->headline() }}</option>
                    @endforeach
                </x-forms.select>
                <x-forms.error for="type" />
            </div>

            <div>
                <x-forms.label for="quantity_change">Quantity Change</x-forms.label>
                <x-forms.input type="number" id="quantity_change" wire:model="quantity_change" placeholder="Positive to add, negative to remove" required />
                <x-forms.error for="quantity_change" />
            </div>

            <div>
                <x-forms.label for="reason">Reason</x-forms.label>
                <x-forms.input type="text" id="reason" wire:model="reason" />
                <x-forms.error for="reason" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <x-ui.button type="button" variant="secondary" wire:click="$set('showAdjustModal', false)">Cancel</x-ui.button>
                <x-ui.button type="submit" loading-text="Saving...">Save</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
