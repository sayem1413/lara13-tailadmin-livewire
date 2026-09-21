<?php

use App\Livewire\Media\MediaPicker;
use App\Models\LibraryAsset;
use App\Models\Permission\Permission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('forbids opening the picker without the admin.media.index permission', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MediaPicker::class)
        ->call('open')
        ->assertForbidden();
});

it('opens the picker for a user with the admin.media.index permission', function () {
    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    Livewire::actingAs($actor)
        ->test(MediaPicker::class)
        ->call('open')
        ->assertSet('show', true);
});

it('dispatches media-picked with the selected asset details', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $asset = LibraryAsset::create(['uploaded_by' => $actor->id]);
    $asset->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('file');

    Livewire::actingAs($actor)
        ->test(MediaPicker::class)
        ->call('pick', $asset->id)
        ->assertSet('show', false)
        ->assertDispatched('media-picked', function (string $name, array $params) use ($asset) {
            return $params['id'] === $asset->id && $params['name'] === 'photo.jpg';
        });
});

it('uploads a new file and immediately picks it', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    Livewire::actingAs($actor)
        ->test(MediaPicker::class)
        ->set('newFile', UploadedFile::fake()->image('new-photo.jpg'))
        ->assertHasNoErrors()
        ->assertDispatched('media-picked', function (string $name, array $params) {
            return $params['name'] === 'new-photo.jpg';
        });

    expect(LibraryAsset::count())->toBe(1);
});

it('rejects a disallowed file extension when uploading through the picker', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    Livewire::actingAs($actor)
        ->test(MediaPicker::class)
        ->set('newFile', UploadedFile::fake()->create('shell.php', 10))
        ->assertHasErrors(['newFile']);

    expect(LibraryAsset::count())->toBe(0);
});

it('rejects a file whose content is sniffed as disallowed even with an allowed extension and a spoofed client MIME type when uploading through the picker', function () {
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
        ->test(MediaPicker::class)
        ->set('newFile', $file)
        ->assertHasErrors(['newFile']);

    expect(LibraryAsset::count())->toBe(0);
});

it('forbids uploading through the picker without the admin.media.index permission, independently of open()', function () {
    Storage::fake('public');

    $unauthorized = User::factory()->create();

    Livewire::actingAs($unauthorized)
        ->test(MediaPicker::class)
        ->set('newFile', UploadedFile::fake()->image('sneaky.jpg'))
        ->assertForbidden();

    expect(LibraryAsset::count())->toBe(0);
});

it('renders an empty asset listing for a viewer without the admin.media.index permission', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $owner = User::factory()->create();
    $owner->givePermissionTo('admin.media.index');

    $asset = LibraryAsset::create(['uploaded_by' => $owner->id]);
    $asset->addMedia(UploadedFile::fake()->image('secret-photo.jpg'))->toMediaCollection('file');

    $unauthorized = User::factory()->create();

    // render() runs on mount too, so this also proves mounting the
    // component still succeeds for a user without the permission (only
    // the listing it renders is gated) - it does not throw/403 here.
    Livewire::actingAs($unauthorized)
        ->test(MediaPicker::class)
        ->assertDontSee('secret-photo.jpg')
        ->assertDontSee($asset->url());
});

it('renders the full asset listing for a viewer with the admin.media.index permission', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $asset = LibraryAsset::create(['uploaded_by' => $actor->id]);
    $asset->addMedia(UploadedFile::fake()->image('visible-photo.jpg'))->toMediaCollection('file');

    Livewire::actingAs($actor)
        ->test(MediaPicker::class)
        ->assertSee('visible-photo.jpg');
});

it('uploads a well-formed SVG through the picker and stores its sanitized content', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.media.index');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="1" height="1" /></svg>';

    Livewire::actingAs($actor)
        ->test(MediaPicker::class)
        ->set('newFile', UploadedFile::fake()->createWithContent('icon.svg', $svg))
        ->assertHasNoErrors()
        ->assertDispatched('media-picked', function (string $name, array $params) {
            return $params['name'] === 'icon.svg';
        });

    $asset = LibraryAsset::sole();
    $stored = file_get_contents($asset->file()->getPath());

    expect($stored)
        ->not->toContain('<script')
        ->not->toContain('onload');
});

it('forbids picking an existing asset without the admin.media.index permission', function () {
    Storage::fake('public');

    Permission::findOrCreate('admin.media.index');
    $owner = User::factory()->create();
    $owner->givePermissionTo('admin.media.index');

    $asset = LibraryAsset::create(['uploaded_by' => $owner->id]);
    $asset->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('file');

    $unauthorized = User::factory()->create();

    Livewire::actingAs($unauthorized)
        ->test(MediaPicker::class)
        ->call('pick', $asset->id)
        ->assertForbidden();
});
