<div>
    <x-ui.button type="button" variant="secondary" wire:click="open">{{ $label }}</x-ui.button>

    <x-ui.modal wire-model="show" title="Choose or upload a file" max-width="lg">
        <div class="mb-4">
            <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search files..." />
        </div>

        <div class="mb-4">
            <x-forms.file-upload wire:model="newFile" accept="*/*" :max-size-mb="10" />
            <x-forms.error for="newFile" />
        </div>

        <div class="grid max-h-80 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4">
            @forelse ($assets as $asset)
                @php $media = $asset->media->first(); @endphp
                <button
                    type="button"
                    wire:key="picker-asset-{{ $asset->id }}"
                    wire:click="pick({{ $asset->id }})"
                    class="overflow-hidden rounded-lg border border-gray-200 hover:border-brand-500 dark:border-white/10"
                >
                    <div class="flex aspect-square items-center justify-center bg-gray-50 dark:bg-white/[0.02]">
                        @if ($media && str_starts_with($media->mime_type ?? '', 'image/'))
                            <img src="{{ $media->getUrl() }}" alt="{{ $media->file_name }}" class="size-full object-cover" />
                        @else
                            <x-ui.icon name="upload-cloud" class="size-6 text-gray-300 dark:text-white/20" />
                        @endif
                    </div>
                    <p class="truncate p-1 text-xs text-gray-600 dark:text-gray-300">{{ $media?->file_name }}</p>
                </button>
            @empty
                <p class="col-span-full py-6 text-center text-sm text-gray-500 dark:text-gray-400">No files yet.</p>
            @endforelse
        </div>
    </x-ui.modal>
</div>
