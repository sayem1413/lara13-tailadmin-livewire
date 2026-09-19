<?php

namespace App\Livewire\Admin\Media;

use App\Models\LibraryAsset;
use App\Services\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class MediaIndex extends Component
{
    use WithFileUploads;

    #[Url]
    public string $search = '';

    public int $perPage = 24;

    public bool $showUploadModal = false;

    public mixed $newFile = null;

    public function mount(): void
    {
        Gate::authorize('admin.media.index');
    }

    public function updatingSearch(): void
    {
        $this->perPage = 24;
    }

    public function loadMore(): void
    {
        $this->perPage += 24;
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

        $mediaService->upload($this->newFile, (int) auth()->id());

        $this->reset('newFile');
        $this->showUploadModal = false;

        $this->dispatch('toast', type: 'success', message: 'File uploaded.');
    }

    public function delete(LibraryAsset $libraryAsset, MediaService $mediaService): void
    {
        Gate::authorize('delete', $libraryAsset);

        $mediaService->delete($libraryAsset);

        $this->dispatch('toast', type: 'success', message: 'File deleted.');
    }

    public function render(MediaService $mediaService): View
    {
        return view('livewire.admin.media.media-index', [
            'assets' => $mediaService->paginate($this->search ?: null, $this->perPage),
        ]);
    }
}
