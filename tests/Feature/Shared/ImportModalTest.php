<?php

use App\Livewire\Shared\ImportModal;
use App\Models\Permission\Permission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function makeUsersCsv(array $rows): UploadedFile
{
    $csv = "Name,Email\n";

    foreach ($rows as $row) {
        $csv .= implode(',', $row)."\n";
    }

    return UploadedFile::fake()->createWithContent('users.csv', $csv);
}

it('forbids opening the modal without the given permission', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ImportModal::class, ['modelClass' => User::class, 'permission' => 'admin.users.import'])
        ->call('open')
        ->assertForbidden();
});

it('imports a valid file and dispatches success feedback', function () {
    Permission::findOrCreate('admin.users.import');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.import');

    $file = makeUsersCsv([['Jane Doe', 'jane@example.com']]);

    Livewire::actingAs($actor)
        ->test(ImportModal::class, ['modelClass' => User::class, 'permission' => 'admin.users.import'])
        ->call('open')
        ->set('file', $file)
        ->assertSet('importedCount', 1)
        ->assertSet('failures', [])
        ->assertSet('show', false)
        ->assertDispatched('imported')
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('rejects a file larger than the 5MB server-side limit', function () {
    Permission::findOrCreate('admin.users.import');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.import');

    $file = UploadedFile::fake()->create('users.csv', 5121);

    Livewire::actingAs($actor)
        ->test(ImportModal::class, ['modelClass' => User::class, 'permission' => 'admin.users.import'])
        ->call('open')
        ->set('file', $file)
        ->assertHasErrors(['file']);
});

it('enforces the 5MB cap through the component\'s own server-side rules(), not a client-side hint', function () {
    Permission::findOrCreate('admin.users.import');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.import');

    // Livewire::test() drives the component's PHP methods directly and never
    // loads a browser or executes the dropzone's Alpine/JS size hint, so a
    // validation failure here can only be produced by rules()'s own
    // server-side "max:5120" rule.
    $file = UploadedFile::fake()->create('big.csv', 5200);

    Livewire::actingAs($actor)
        ->test(ImportModal::class, ['modelClass' => User::class, 'permission' => 'admin.users.import'])
        ->call('open')
        ->set('file', $file)
        // Asserting the specific "max" rule (not just any error on "file")
        // proves the size cap itself tripped, rather than some unrelated
        // rule coincidentally failing on the fake upload.
        ->assertHasErrors(['file' => 'max']);

    // Only $actor (created above) should exist - the oversized upload must
    // never have reached ImportService::import().
    expect(User::count())->toBe(1);
});

it('reports failures and keeps the modal open when a row is invalid', function () {
    Permission::findOrCreate('admin.users.import');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.import');

    User::factory()->create(['email' => 'taken@example.com']);

    $file = makeUsersCsv([['Someone', 'taken@example.com']]);

    Livewire::actingAs($actor)
        ->test(ImportModal::class, ['modelClass' => User::class, 'permission' => 'admin.users.import'])
        ->call('open')
        ->set('file', $file)
        ->assertSet('importedCount', 0)
        ->assertSet('show', true)
        ->assertNotDispatched('imported')
        ->assertSet('failures', fn (array $failures) => count($failures) === 1 && $failures[0]['row'] === 2);
});
