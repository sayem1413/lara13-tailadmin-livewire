<?php

namespace App\Services\Media;

use App\Exceptions\Media\InvalidSvgException;
use App\Models\LibraryAsset;
use App\Repositories\Interfaces\Media\MediaRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaService
{
    public function __construct(
        protected MediaRepositoryInterface $mediaRepository,
        protected SvgSanitizer $svgSanitizer,
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
        try {
            if ($this->isSvg($file)) {
                // An SVG is XML a browser will execute if opened directly
                // (script tags, on* handlers, external references) - never
                // persist the uploaded bytes as-is. Sanitize first and
                // store the cleaned content instead of the original file.
                $sanitized = $this->svgSanitizer->sanitize((string) file_get_contents($file->getRealPath()));

                return $this->mediaRepository->createFromString(
                    $sanitized,
                    $file->getClientOriginalName(),
                    $uploadedBy
                );
            }

            return $this->mediaRepository->createFromUpload($file, $uploadedBy);
        } catch (FileCannotBeAdded) {
            // Thrown by spatie/laravel-medialibrary for a genuinely failed
            // upload (disallowed file name, disk unreachable, etc). Its own
            // message can include local file paths, so it isn't safe to
            // show verbatim to the user.
            throw ValidationException::withMessages([
                'newFile' => 'This file could not be uploaded. Please try a different file.',
            ]);
        } catch (InvalidSvgException) {
            // The file passed the extension/content-sniffed MIME checks
            // (so it looks like an SVG) but isn't well-formed XML - reject
            // it the same way as any other invalid upload, not a 500.
            throw ValidationException::withMessages([
                'newFile' => 'This SVG file could not be processed. Please try a different file.',
            ]);
        }
    }

    protected function isSvg(UploadedFile $file): bool
    {
        return strtolower((string) $file->getClientOriginalExtension()) === 'svg'
            || $file->getMimeType() === 'image/svg+xml';
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
