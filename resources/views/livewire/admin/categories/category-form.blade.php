<div>
    <form wire:submit="save">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ $categoryId ? 'Edit Category' : 'Add Category' }}
            </h1>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.categories.index') }}">
                    <x-ui.button type="button" variant="secondary">Cancel</x-ui.button>
                </a>
                <x-ui.button type="submit" loading-text="Saving...">Save</x-ui.button>
            </div>
        </div>

        <x-forms.error for="category" />

        <x-ui.card class="max-w-2xl space-y-5">
            <div>
                <x-forms.label for="parent_id">Parent Category</x-forms.label>
                <x-forms.select searchable wire:model="parent_id" id="parent_id">
                    <option value="">No parent (top level)</option>
                    @foreach ($parentOptions as $option)
                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </x-forms.select>
                <x-forms.error for="parent_id" />
            </div>

            <div>
                <x-forms.label for="name">Name</x-forms.label>
                <x-forms.input type="text" icon="tag" id="name" wire:model.live="name" required />
                <x-forms.error for="name" />
            </div>

            <div>
                <x-forms.label for="slug">Slug</x-forms.label>
                <x-forms.input type="text" id="slug" wire:model="slug" placeholder="Generated from the name if left blank" />
                <x-forms.error for="slug" />
            </div>

            <div>
                <x-forms.label for="description">Description</x-forms.label>
                <x-forms.textarea id="description" wire:model="description" rows="3" />
                <x-forms.error for="description" />
            </div>

            <div>
                <x-forms.label for="sort_order">Sort Order</x-forms.label>
                <x-forms.input type="number" id="sort_order" wire:model="sort_order" min="0" />
                <x-forms.error for="sort_order" />
            </div>
        </x-ui.card>
    </form>
</div>
