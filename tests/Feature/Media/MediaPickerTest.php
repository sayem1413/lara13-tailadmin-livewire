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
