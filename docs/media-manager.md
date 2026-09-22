# Media Manager

Covers the upload allowlist shared by the two upload surfaces
(`app/Livewire/Admin/Media/MediaIndex.php`, the full library page, and
`app/Livewire/Media/MediaPicker.php`, the `<x-media.picker>` modal a
consuming component embeds), the SVG sanitization step both of them now
route through, and `MediaPicker`'s defensive render-time authorization gate.

## The upload allowlist

Both components validate `newFile` with the same two rules, kept in sync by
hand (see the "keep this allowlist in sync" comment on each `rules()`
method):

```php
'extensions:jpg,jpeg,png,gif,webp,bmp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip',
'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/x-ms-bmp,image/svg+xml,application/pdf,...',
```

- `extensions:` checks the filename the user chose.
- `mimetypes:` content-sniffs the actual upload.

Both are needed: `extensions:` alone trusts the filename, so a script or
executable renamed to `photo.jpg` would sail through. `mimetypes:` alone
would accept a correctly-typed file under any name, which is harder for an
admin to reason about when browsing the library later. Together, a file has
to look right by name *and* by content before it's accepted.

## SVG sanitization

SVG is on the allowlist, but it needs a step the other formats don't: unlike
a raster image, an SVG file is XML, and a browser that opens one directly
will happily execute what's embedded in it. A malicious upload can carry:

- **`<script>` elements** - arbitrary JavaScript, exactly as in HTML.
- **Event-handler attributes** (`onload`, `onclick`, `onerror`, ...) - run
  JavaScript without needing a `<script>` tag at all.
- **External references** (`<image href="http://evil.example/x">`,
  `<use href="...">`) - pull in and execute arbitrary remote content when
  the file is rendered.
- **`<foreignObject>`** - a standard SVG element that can embed arbitrary
  HTML (including its own `<script>`) inside otherwise-inert markup; a
  common SVG sandbox-escape vector.

`app/Services/Media/SvgSanitizer.php` strips all four, using PHP's built-in
`ext-dom` (`DOMDocument`) - no new Composer dependency. It:

1. Parses the content as XML via `DOMDocument::loadXML()` with
   `LIBXML_NONET` (blocks the parser from making any network request), and
   deliberately without `LIBXML_DTDLOAD`/`LIBXML_NOENT`, so no external DTD
   is ever fetched and no entity is expanded. (The old
   `libxml_disable_entity_loader()` toggle is deprecated and a no-op on
   modern PHP - external entity loading is already off by default - so
   this is defense in depth, not the only guard.)
2. Throws `App\Exceptions\Media\InvalidSvgException` if the content isn't
   well-formed XML, or its root element isn't `<svg>`. This matters even
   though the file already passed the `extensions:`/`mimetypes:` rules:
   MIME sniffing only inspects the first few bytes and doesn't fully parse
   the document, so a payload can look enough like an SVG to pass validation
   while still being malformed. Rejecting it here is itself a security
   check, not just error handling for a broken file.
3. Removes every `<script>` and `<foreignObject>` element anywhere in the
   tree (not just at the root).
4. Removes any attribute whose name starts with `on`, case-insensitively,
   from any element.
5. Removes any `href`/`xlink:href` attribute whose value isn't a
   same-document fragment (`#id`) or a `data:image/...` URI - the attribute
   is stripped outright rather than rewritten, since there's no way to know
   what a remote reference *should* resolve to.
6. Returns the cleaned document via `saveXML()`.

### Where it's wired in

`MediaService::upload()` (`app/Services/Media/MediaService.php`) checks
whether the incoming file is SVG (by client extension or content-sniffed
MIME type) before delegating to the repository. Non-SVG files are
completely unaffected - they still go through
`MediaRepository::createFromUpload()`, passing the original `UploadedFile`
straight to spatie/laravel-medialibrary's `addMedia()`.

For an SVG, the file's contents are read, sanitized, and handed to a new
`MediaRepository::createFromString()` method, which uses spatie's
`addMediaFromString()` (rather than writing the sanitized string to a second
temp path and re-wrapping it as an `UploadedFile`) - it already does exactly
what's needed here: write a string to a temp file and hand it to the same
`FileAdder` pipeline `addMedia()` uses, just without carrying over the
original (untrusted) file. `usingName()`/`usingFileName()` are set
afterwards to the original filename, since `addMediaFromString()` otherwise
names the media after its own generated temp path.

If `SvgSanitizer` throws `InvalidSvgException`, `MediaService::upload()`
catches it and raises the same
`ValidationException::withMessages(['newFile' => ...])` shape it already
uses when spatie's own `FileCannotBeAdded` is thrown for a genuinely failed
upload - the caller (either Livewire component) sees a normal validation
error on `newFile`, never a raw 500.

## MediaPicker's defensive render-time gate

`MediaPicker` backs `<x-media.picker>`, a modal a host page embeds and shows
conditionally with Alpine (`x-show`/CSS). `open()`, `pick()`, and the upload
handler (`updatedNewFile()`) have always been individually gated behind the
`admin.media.index` permission with `Gate::authorize()`.

That's not enough on its own: `render()` runs on *every* Livewire request
for the component - including ones that have nothing to do with actually
opening the picker - and it always fetched and rendered the full asset
listing (filenames and URLs) into the HTML payload. A hidden modal is still
present in the DOM; relying on CSS alone to keep an unauthorized viewer from
seeing that listing means it was already in their browser.

`render()` now checks the permission itself:

```php
$assets = Gate::allows('admin.media.index')
    ? $mediaService->paginate($this->search ?: null, 12)
    : new LengthAwarePaginator([], 0, 12);
```

This deliberately uses `Gate::allows()`, not `Gate::authorize()`: `render()`
must not throw for an unauthorized viewer, since Livewire calls it as part
of ordinary component updates unrelated to the picker (mounting the
component, any sibling interaction that triggers a re-render). Throwing
there would break the whole host page. Instead, the listing is silently
empty - `Gate::authorize()` remains the right choice for `open()`/`pick()`/
`updatedNewFile()`, which are direct user actions that should fail loudly.

The component itself still mounts successfully for any authenticated user
regardless of permission (there's no `mount()`-level gate) - only the
listing `render()` returns, and the actions above, are gated.
