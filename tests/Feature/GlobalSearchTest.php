<?php

use App\Livewire\Layout\GlobalSearch;
use App\Models\Permission\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;

it('finds a matching user for an actor with the admin.users.index permission', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    User::factory()->create(['name' => 'Findable Person']);

    Livewire::actingAs($actor)
        ->test(GlobalSearch::class)
        ->set('query', 'Findable')
        ->assertSet('results', function (Collection $groups) {
            $users = $groups->firstWhere('module', 'Users');

            return $users && $users['results']->pluck('title')->contains('Findable Person');
        });
});

it('groups results by module', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    User::factory()->create(['name' => 'Findable Person']);

    Livewire::actingAs($actor)
        ->test(GlobalSearch::class)
        ->set('query', 'Findable')
        ->assertSet('results', function (Collection $groups) {
            return $groups->every(fn (array $group) => array_key_exists('module', $group) && $group['results'] instanceof Collection);
        });
});

it('hides the Users module from search for an actor without the admin.users.index permission', function () {
    $actor = User::factory()->create();
    User::factory()->create(['name' => 'Findable Person']);

    Livewire::actingAs($actor)
        ->test(GlobalSearch::class)
        ->set('query', 'Findable')
        ->assertSet('results', fn (Collection $results) => $results->isEmpty());
});
