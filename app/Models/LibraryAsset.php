<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A general-purpose file uploaded through the Media Manager, not yet (or
 * not necessarily ever) attached to a specific business record. Each row
 * wraps exactly one file via spatie/medialibrary's own "media" table -
 * this model's own columns just track who uploaded it and when.
 */
class LibraryAsset extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'uploaded_by',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    public function url(): ?string
    {
        return $this->getFirstMediaUrl('file') ?: null;
    }
}
