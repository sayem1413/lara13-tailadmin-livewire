<?php

use App\Livewire\Layout\GlobalSearch;
use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\Search\SearchService;
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

it('does not error when the search term is far longer than any real result', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    Livewire::actingAs($actor)
        ->test(GlobalSearch::class)
        ->set('query', str_repeat('a', 5000))
        ->assertOk()
        ->assertSet('results', fn (Collection $results) => $results->isEmpty());
});

it('points a Users search result at the show route, reachable by a view-only admin without edit permission', function () {
    Permission::findOrCreate('admin.users.index');
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('admin.users.index');

    $target = User::factory()->create(['name' => 'Reachable Person']);

    $results = app(SearchService::class)->search($viewer, 'Reachable');

    $users = $results->firstWhere('module', 'Users');
    $url = $users['results']->firstWhere('title', 'Reachable Person')['url'];

    expect($url)->toBe(route('admin.users.show', $target));

    $this->actingAs($viewer)->get($url)->assertOk();
});

it('shows the Edit link on the show page to an editor but not to a view-only admin', function () {
    Permission::findOrCreate('admin.users.index');
    Permission::findOrCreate('admin.users.edit');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('admin.users.index');

    $editor = User::factory()->create();
    $editor->givePermissionTo(['admin.users.index', 'admin.users.edit']);

    $target = User::factory()->create();

    $this->actingAs($viewer)->get(route('admin.users.show', $target))
        ->assertOk()
        ->assertDontSee('Edit User');

    $this->actingAs($editor)->get(route('admin.users.show', $target))
        ->assertOk()
        ->assertSee('Edit User');
});
