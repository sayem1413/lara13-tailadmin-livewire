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
