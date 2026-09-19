<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Media Library</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Upload and manage files shared across the application.</p>
        </div>

        <x-ui.button wire:click="$set('showUploadModal', true)">Upload File</x-ui.button>
    </div>

    <x-ui.card>
        <div class="mb-4 max-w-xs">
            <x-forms.input type="search" icon="search" wire:model.live.debounce.300ms="search" placeholder="Search by file name..." />
        </div>

        @if ($assets->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No files uploaded yet.</p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ($assets as $asset)
                    @php $media = $asset->media->first(); @endphp

                    <div wire:key="asset-{{ $asset->id }}" class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                        <div class="flex aspect-square items-center justify-center bg-gray-50 dark:bg-white/[0.02]">
                            @if ($media && str_starts_with($media->mime_type ?? '', 'image/'))
                                <img src="{{ $media->getUrl() }}" alt="{{ $media->name }}" class="size-full object-cover" />
                            @else
                                <x-ui.icon name="upload-cloud" class="size-8 text-gray-300 dark:text-white/20" />
                            @endif
                        </div>

                        <div class="p-2">
                            <p class="truncate text-xs font-medium text-gray-700 dark:text-gray-300" title="{{ $media?->file_name }}">
                                {{ $media?->file_name ?? 'Untitled' }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $media?->human_readable_size }}</p>
                        </div>

                        <button
                            type="button"
                            wire:click="delete({{ $asset->id }})"
                            data-confirm="delete"
                            data-confirm-entity="File"
                            data-confirm-name="{{ $media?->file_name }}"
                            class="absolute top-2 right-2 rounded-lg bg-white/90 p-1.5 text-red-600 opacity-0 shadow-sm transition-opacity group-hover:opacity-100 hover:bg-white dark:bg-gray-800/90 dark:hover:bg-gray-800"
                        >
                            <x-ui.icon name="x-circle" class="size-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <x-ui.load-more :paginator="$assets" />
    </x-ui.card>

    <x-ui.modal wire-model="showUploadModal" title="Upload File">
        <x-forms.file-upload wire:model="newFile" accept="*/*" :max-size-mb="10" />
        <x-forms.error for="newFile" />
    </x-ui.modal>
</div>
