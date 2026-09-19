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
        ->assertSet('results', fn (Collection $results) => $results->pluck('title')->contains('Findable Person'));
});

it('hides the Users module from search for an actor without the admin.users.index permission', function () {
    $actor = User::factory()->create();
    User::factory()->create(['name' => 'Findable Person']);

    Livewire::actingAs($actor)
        ->test(GlobalSearch::class)
        ->set('query', 'Findable')
        ->assertSet('results', fn (Collection $results) => $results->isEmpty());
});
