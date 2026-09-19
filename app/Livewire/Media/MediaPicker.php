<?php

namespace App\Livewire\Media;

use App\Models\LibraryAsset;
use App\Services\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Backs <x-media.picker>: a modal a consuming component can open to either
 * pick an existing library file or upload a new one, and receive it back
 * via the "media-picked" browser event ({id, url, name}) without needing
 * to know anything about the Media Library's own storage internals.
 */
class MediaPicker extends Component
{
    use WithFileUploads;

    public string $label = 'Choose File';

    public bool $show = false;

    public string $search = '';

    public mixed $newFile = null;

    public function open(): void
    {
        Gate::authorize('admin.media.index');

        $this->show = true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'newFile' => ['required', 'file', 'max:10240'],
        ];
    }

    public function updatedNewFile(MediaService $mediaService): void
    {
        $this->validate();

        $asset = $mediaService->upload($this->newFile, (int) auth()->id());

        $this->reset('newFile');
        $this->pick($asset);
    }

    public function pick(LibraryAsset $libraryAsset): void
    {
        $this->show = false;

        $this->dispatch(
            'media-picked',
            id: $libraryAsset->id,
            url: $libraryAsset->url(),
            name: $libraryAsset->file()?->file_name,
        );
    }

    public function render(MediaService $mediaService): View
    {
        return view('livewire.media.media-picker', [
            'assets' => $mediaService->paginate($this->search ?: null, 12),
        ]);
    }
}
