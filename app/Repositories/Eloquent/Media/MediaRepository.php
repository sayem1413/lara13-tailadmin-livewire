<?php

namespace App\Repositories\Eloquent\Media;

use App\Models\LibraryAsset;
use App\Repositories\Interfaces\Media\MediaRepositoryInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

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
        $query = $this->model->query()->with(['media', 'uploader'])->latest();

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
        $asset = $this->model->create(['uploaded_by' => $uploadedBy]);

        $asset->addMedia($file)->toMediaCollection('file');

        return $asset->refresh();
    }

    public function delete(LibraryAsset $asset): bool
    {
        return (bool) $asset->delete();
    }
}
