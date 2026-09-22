<?php

namespace App\Repositories\Eloquent\Media;

use App\Models\LibraryAsset;
use App\Repositories\Interfaces\Media\MediaRepositoryInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MediaRepository implements MediaRepositoryInterface
{
    public function __construct(
        protected LibraryAsset $model
    ) {}

    /**
     * @return LengthAwarePaginator<int, LibraryAsset>
     */
    public function paginate(?string $search = null, int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->model->query()->with('media')->latest();

        if (! empty($search)) {
            $query->whereHas('media', function (Builder $query) use ($search) {
                $query->where('file_name', 'like', "%{$search}%");
            });
        }

        // Infinite-scroll (see MediaIndex::loadMore()) grows $perPage instead
        // of advancing the page, matching the pattern used across every
        // other index page in this app.
        return $query->paginate($perPage, page: 1);
    }

    public function findOrFail(int $id): LibraryAsset
    {
        return $this->model->findOrFail($id);
    }

    public function createFromUpload(UploadedFile $file, ?int $uploadedBy): LibraryAsset
    {
        // Without a transaction, a failure partway through addMedia() (disk
        // full, disallowed extension, etc.) would leave the library_assets
        // row committed with no file attached to it.
        return DB::transaction(function () use ($file, $uploadedBy) {
            $asset = $this->model->create(['uploaded_by' => $uploadedBy]);

            $asset->addMedia($file)->toMediaCollection('file');

            return $asset->refresh();
        });
    }

    public function createFromString(string $contents, string $filename, ?int $uploadedBy): LibraryAsset
    {
        // Same reasoning as createFromUpload(): wrap the create + attach in
        // a transaction so a failure partway through addMediaFromString()
        // can't leave a library_assets row with no file attached to it.
        return DB::transaction(function () use ($contents, $filename, $uploadedBy) {
            $asset = $this->model->create(['uploaded_by' => $uploadedBy]);

            // addMediaFromString() defaults both the file name and the
            // media's display name off its own temp file path, so both
            // need to be overridden with the original upload's name -
            // same as spatie's own addMediaFromUrl() does internally.
            $asset->addMediaFromString($contents)
                ->usingName(pathinfo($filename, PATHINFO_FILENAME))
                ->usingFileName($filename)
                ->toMediaCollection('file');

            return $asset->refresh();
        });
    }

    public function delete(LibraryAsset $asset): bool
    {
        return (bool) $asset->delete();
    }
}
