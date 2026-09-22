<?php

namespace App\Exceptions\Media;

use RuntimeException;

/**
 * Thrown by SvgSanitizer when an uploaded file claims to be SVG (by
 * extension and content-sniffed MIME type, so it already passed the
 * Livewire component's own validation rules) but doesn't actually parse as
 * well-formed XML. Treating this as a security check - not just error
 * handling - matters because MIME sniffing only inspects the first bytes
 * of a file; a payload can look enough like an SVG to fool it while still
 * being malformed enough to behave unpredictably once written to disk.
 *
 * Caught in MediaService::upload() and surfaced as a ValidationException,
 * the same way spatie/laravel-medialibrary's own FileCannotBeAdded is.
 */
class InvalidSvgException extends RuntimeException
{
    public static function forMalformedXml(): self
    {
        return new self('The uploaded file is not a valid SVG.');
    }
}
