<div>
    <form wire:submit="save">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ $productId ? 'Edit Product' : 'Add Product' }}
            </h1>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.products.index') }}">
                    <x-ui.button type="button" variant="secondary">Cancel</x-ui.button>
                </a>
                <x-ui.button type="submit" loading-text="Saving...">Save</x-ui.button>
            </div>
        </div>

        <x-forms.error for="product" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card title="Details" class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <x-forms.label for="name">Name</x-forms.label>
                            <x-forms.input type="text" id="name" wire:model.live="name" required />
                            <x-forms.error for="name" />
                        </div>

                        <div>
                            <x-forms.label for="slug">Slug</x-forms.label>
                            <x-forms.input type="text" id="slug" wire:model="slug" placeholder="Generated from the name if left blank" />
                            <x-forms.error for="slug" />
                        </div>

                        <div>
                            <x-forms.label for="sku">SKU</x-forms.label>
                            <x-forms.input type="text" id="sku" wire:model="sku" placeholder="Generated automatically if left blank" />
                            <x-forms.error for="sku" />
                        </div>

                        <div>
                            <x-forms.label for="barcode">Barcode</x-forms.label>
                            <x-forms.input type="text" id="barcode" wire:model="barcode" />
                            <x-forms.error for="barcode" />
                        </div>

                        <div>
                            <x-forms.label for="unit">Unit</x-forms.label>
                            <x-forms.select wire:model="unit" id="unit">
                                @foreach (\App\Enums\ProductUnit::cases() as $case)
                                    <option value="{{ $case->value }}">{{ str($case->name)->headline() }}</option>
                                @endforeach
                            </x-forms.select>
                            <x-forms.error for="unit" />
                        </div>

                        <div>
                            <x-forms.label for="weight">Weight (kg)</x-forms.label>
                            <x-forms.input type="number" step="0.001" id="weight" wire:model="weight" />
                            <x-forms.error for="weight" />
                        </div>
                    </div>

                    <div>
                        <x-forms.label for="short_description">Short Description</x-forms.label>
                        <x-forms.textarea id="short_description" wire:model="short_description" rows="2" />
                        <x-forms.error for="short_description" />
                    </div>

                    <div>
                        <x-forms.label for="description">Description</x-forms.label>
                        <x-forms.textarea id="description" wire:model="description" rows="4" />
                        <x-forms.error for="description" />
                    </div>
                </x-ui.card>

                <x-ui.card title="Pricing" class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <x-forms.label for="price">Price</x-forms.label>
                            <x-forms.input type="number" step="0.01" id="price" wire:model="price" required />
                            <x-forms.error for="price" />
                        </div>

                        <div>
                            <x-forms.label for="compare_at_price">Compare-at Price</x-forms.label>
                            <x-forms.input type="number" step="0.01" id="compare_at_price" wire:model="compare_at_price" />
                            <x-forms.error for="compare_at_price" />
                        </div>

                        <div>
                            <x-forms.label for="cost_price">Cost Price</x-forms.label>
                            <x-forms.input type="number" step="0.01" id="cost_price" wire:model="cost_price" />
                            <x-forms.error for="cost_price" />
                        </div>

                        <div class="sm:col-span-3">
                            <x-forms.label for="tax_rate">Tax / VAT Rate (%)</x-forms.label>
                            <x-forms.input type="number" step="0.01" id="tax_rate" wire:model="tax_rate" placeholder="Leave blank to use the default VAT rate" />
                            <x-forms.error for="tax_rate" />
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card title="Inventory" class="space-y-5">
                    <x-forms.toggle wire:model.live="track_inventory" label="Track inventory for this product" />

                    @if ($track_inventory)
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            @unless ($productId)
                                <div>
                                    <x-forms.label for="initial_stock">Initial Stock</x-forms.label>
                                    <x-forms.input type="number" id="initial_stock" wire:model="initial_stock" min="0" />
                                    <x-forms.error for="initial_stock" />
                                </div>
                            @endunless

                            <div>
                                <x-forms.label for="low_stock_threshold">Low Stock Threshold</x-forms.label>
                                <x-forms.input type="number" id="low_stock_threshold" wire:model="low_stock_threshold" min="0" placeholder="Uses the default threshold if left blank" />
                                <x-forms.error for="low_stock_threshold" />
                            </div>
                        </div>

                        @if ($productId)
                            <a href="{{ route('admin.inventory.index') }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">
                                Adjust stock from the Inventory page →
                            </a>
                        @endif
                    @endif
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-ui.card title="Categories">
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        @forelse ($categoryOptions as $option)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <x-forms.checkbox wire:model="selectedCategories" value="{{ $option['id'] }}" />
                                {{ $option['label'] }}
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No categories yet.</p>
                        @endforelse
                    </div>
                    <x-forms.error for="selectedCategories" />
                </x-ui.card>

                <x-ui.card title="Images">
                    @if ($productId && $existingImages->isNotEmpty())
                        <div class="mb-4 grid grid-cols-3 gap-2">
                            @foreach ($existingImages as $media)
                                <div class="group relative">
                                    <img src="{{ $media->getUrl() }}" alt="" class="aspect-square w-full rounded-lg object-cover" />
                                    <button
                                        type="button"
                                        wire:click="removeImage({{ $media->id }})"
                                        data-confirm="delete"
                                        data-confirm-entity="image"
                                        class="absolute top-1 right-1 rounded-full bg-gray-900/70 p-1 text-white opacity-0 group-hover:opacity-100"
                                    >
                                        <x-ui.icon name="x-circle" class="size-4" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <input type="file" wire:model="newImages" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 dark:text-gray-300" />
                    <x-forms.error for="newImages.*" />

                    @if ($newImages)
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ($newImages as $image)
                                <img src="{{ $image->temporaryUrl() }}" alt="" class="aspect-square w-full rounded-lg object-cover" />
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </div>
        </div>
    </form>
</div>
