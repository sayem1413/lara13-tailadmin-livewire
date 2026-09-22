<?php

use App\Livewire\Admin\Media\MediaIndex;
use App\Models\LibraryAsset;
use App\Models\Permission\Permission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.media.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.media.index permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.media.index'))->assertForbidden();
});

it('uploads a file and lists it in the library', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', UploadedFile::fake()->image('photo.jpg'))
        ->assertHasNoErrors()
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        })
        ->assertSee('photo.jpg');

    expect(LibraryAsset::count())->toBe(1);
});

it('rejects a disallowed file extension when uploading', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', UploadedFile::fake()->create('shell.php', 10))
        ->assertHasErrors(['newFile']);

    expect(LibraryAsset::count())->toBe(0);
});

it('rejects a file whose content is sniffed as disallowed even with an allowed extension and a spoofed client MIME type', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, '<?php echo "pwned"; ?>');

    // A real UploadedFile (not the Testing\File fake, whose getMimeType()
    // is faked from the filename extension): the constructor's mimeType
    // argument only sets the CLIENT-declared mime, so getMimeType() below
    // still content-sniffs the actual bytes, same as a real browser upload.
    // Livewire's test `set()` reads a public `name` property off the file
    // (only present on Testing\File), so this anonymous subclass adds one
    // without touching getMimeType().
    $file = new class($path, 'shell.jpg', 'image/jpeg', null, true) extends UploadedFile
    {
        public $name;

        public function __construct($path, $originalName, $mimeType, $error, $test)
        {
            parent::__construct($path, $originalName, $mimeType, $error, $test);

            $this->name = $originalName;
        }
    };

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', $file)
        ->assertHasErrors(['newFile']);

    expect(LibraryAsset::count())->toBe(0);
});

it('sniffs and rejects a disguised SVG payload smuggled inside a plain-text upload, even though its extension and content-sniffed MIME type are both allowed', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $path = tempnam(sys_get_temp_dir(), 'upload');
    // finfo sniffs this as plain text/plain (an allowed mimetype under the
    // allowed .txt extension) precisely because the leading prose keeps it
    // from being recognized as image/svg+xml - which is exactly what would
    // let it slip past MediaService::isSvg()'s extension/MIME checks and
    // get stored completely unsanitized, script tag and all.
    file_put_contents($path, "Meeting notes for Q3 planning.\nAttendees: Alice, Bob.\n<svg onload=alert(1)></svg>");

    $file = new class($path, 'notes.txt', 'text/plain', null, true) extends UploadedFile
    {
        public $name;

        public function __construct($path, $originalName, $mimeType, $error, $test)
        {
            parent::__construct($path, $originalName, $mimeType, $error, $test);

            $this->name = $originalName;
        }
    };

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', $file)
        ->assertHasErrors(['newFile']);

    expect(LibraryAsset::count())->toBe(0);
});

it('uploads a well-formed SVG and stores its sanitized content', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="red" /></svg>';

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', UploadedFile::fake()->createWithContent('icon.svg', $svg))
        ->assertHasNoErrors();

    $asset = LibraryAsset::sole();
    $stored = file_get_contents($asset->file()->getPath());

    expect($stored)
        ->toContain('<rect')
        ->not->toContain('<script')
        ->not->toContain('onload=');
});

it('strips script tags, event-handler attributes, and external references from a malicious SVG upload', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">'
        .'<script>alert(1)</script>'
        .'<image xlink:href="http://evil.example/x" width="1" height="1" />'
        .'</svg>';

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', UploadedFile::fake()->createWithContent('malicious.svg', $svg))
        ->assertHasNoErrors();

    $asset = LibraryAsset::sole();
    $stored = file_get_contents($asset->file()->getPath());

    expect($stored)
        ->not->toContain('<script')
        ->not->toContain('alert(1)')
        ->not->toContain('onload')
        ->not->toContain('evil.example');
});

it('rejects a .svg upload whose content is not valid XML with a clean validation error', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    // Sniffed as image/svg+xml (it starts with a <svg> tag) and passes the
    // extensions/mimetypes rules, but is not well-formed XML (mismatched
    // closing tag) - SvgSanitizer must reject it independently of those
    // rules, not just error handling for a genuinely broken upload.
    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', UploadedFile::fake()->createWithContent('broken.svg', '<svg><rect></svg>'))
        ->assertHasErrors(['newFile']);

    expect(LibraryAsset::count())->toBe(0);
});

it('rolls back the library asset row when the media library rejects the file after it is created', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    // Passes MediaIndex's own extensions/mimetypes rules (it's a real,
    // small JPEG) but is then rejected by spatie/laravel-medialibrary
    // itself once addMedia()->toMediaCollection() runs inside
    // MediaRepository::createFromUpload()'s DB::transaction() - after the
    // LibraryAsset row has already been created. Without a transaction
    // (or if it were rolled back incorrectly) that row would survive even
    // though no file was ever attached to it.
    config(['media-library.max_file_size' => 1]);

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->set('newFile', UploadedFile::fake()->image('photo.jpg'))
        ->assertHasErrors(['newFile']);

    expect(LibraryAsset::count())->toBe(0);
});

it('forbids deleting a file without the admin.media.destroy permission', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    Permission::findOrCreate('admin.media.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $asset = LibraryAsset::create(['uploaded_by' => $actor->id]);
    $asset->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('file');

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->call('delete', $asset->id)
        ->assertForbidden();

    expect(LibraryAsset::count())->toBe(1);
});

it('deletes a file with the admin.media.destroy permission', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    Permission::findOrCreate('admin.media.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.media.index', 'admin.media.destroy']);

    $asset = LibraryAsset::create(['uploaded_by' => $actor->id]);
    $asset->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('file');

    Livewire::actingAs($actor)
        ->test(MediaIndex::class)
        ->call('delete', $asset->id)
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    expect(LibraryAsset::count())->toBe(0);
});
