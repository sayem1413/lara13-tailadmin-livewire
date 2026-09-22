<?php

namespace App\Livewire\Media;

use App\Models\LibraryAsset;
use App\Services\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
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
            // extensions: checks the filename the user chose; mimetypes:
            // content-sniffs the actual upload (see ImportModal for the
            // same reasoning) so a script or executable renamed with an
            // allowed extension is still rejected. Keep this allowlist in
            // sync with MediaIndex's own upload rules.
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
        Gate::authorize('admin.media.index');

        $this->validate();

        $asset = $mediaService->upload($this->newFile, (int) auth()->id());

        $this->reset('newFile');
        $this->pick($asset);
    }

    public function pick(LibraryAsset $libraryAsset): void
    {
        Gate::authorize('admin.media.index');

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
        // render() runs on every Livewire request for this component, not
        // just ones where the modal is actually open - relying on the
        // modal being hidden by x-show/CSS would still ship the full
        // filename+URL listing in the HTML payload to an unauthorized
        // viewer. Gate it here too, and fail closed to an empty listing
        // (not a 403): render() has nothing to do with the user's intent
        // to open the picker, so throwing here would break the host page
        // rather than just hiding data.
        $assets = Gate::allows('admin.media.index')
            ? $mediaService->paginate($this->search ?: null, 12)
            : new LengthAwarePaginator([], 0, 12);

        return view('livewire.media.media-picker', [
            'assets' => $assets,
        ]);
    }
}
