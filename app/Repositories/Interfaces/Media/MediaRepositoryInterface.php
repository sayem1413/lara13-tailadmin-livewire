<?php

namespace App\Repositories\Interfaces\Media;

use App\Models\LibraryAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

interface MediaRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, LibraryAsset>
     */
    public function paginate(?string $search = null, int $perPage = 24): LengthAwarePaginator;

    public function findOrFail(int $id): LibraryAsset;

    public function createFromUpload(UploadedFile $file, ?int $uploadedBy): LibraryAsset;

    public function delete(LibraryAsset $asset): bool;
}
