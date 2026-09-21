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
            // extensions: checks the filename the user chose; mimetypes:
            // content-sniffs the actual upload (see ImportModal for the
            // same reasoning) so a script or executable renamed with an
            // allowed extension is still rejected. Keep this allowlist in
            // sync with MediaPicker's own upload rules.
            'newFile' => [
                'required',
                'file',
                'max:10240',
                'extensions:jpg,jpeg,png,gif,webp,bmp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/x-ms-bmp,image/svg+xml,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv,application/csv,application/zip,application/x-zip-compressed',
            ],
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
