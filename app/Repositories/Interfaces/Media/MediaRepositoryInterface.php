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

    /**
     * Store $contents (already-sanitized, e.g. by SvgSanitizer) as a new
     * library asset's file, rather than an original UploadedFile - used
     * for uploads that need their content rewritten before it's ever
     * persisted, instead of the untrusted original bytes.
     */
    public function createFromString(string $contents, string $filename, ?int $uploadedBy): LibraryAsset;

    public function delete(LibraryAsset $asset): bool;
}
