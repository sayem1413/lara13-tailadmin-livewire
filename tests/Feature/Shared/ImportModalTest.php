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
