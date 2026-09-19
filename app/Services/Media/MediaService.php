<?php

namespace App\Services\Media;

use App\Models\LibraryAsset;
use App\Repositories\Interfaces\Media\MediaRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaService
{
    public function __construct(
        protected MediaRepositoryInterface $mediaRepository
    ) {}

    /**
     * @return LengthAwarePaginator<int, LibraryAsset>
     */
    public function paginate(?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->mediaRepository->paginate($search, $perPage);
    }

    public function upload(UploadedFile $file, ?int $uploadedBy): LibraryAsset
    {
        return $this->mediaRepository->createFromUpload($file, $uploadedBy);
    }

    public function findOrFail(int $id): LibraryAsset
    {
        return $this->mediaRepository->findOrFail($id);
    }

    public function delete(LibraryAsset $asset): bool
    {
        return $this->mediaRepository->delete($asset);
    }

    /**
     * Attach a library asset's file to another model's own media
     * collection, for a module embedding <x-media.picker> to claim a
     * picked (or freshly uploaded) file as its own attachment rather than
     * just linking to the shared library copy.
     */
    public function attachToModel(LibraryAsset $asset, HasMedia&Model $model, string $collection = 'default'): Media
    {
        $file = $asset->file();

        throw_unless($file, new RuntimeException("Library asset {$asset->id} has no file to attach."));

        return $file->copy($model, $collection);
    }
}
