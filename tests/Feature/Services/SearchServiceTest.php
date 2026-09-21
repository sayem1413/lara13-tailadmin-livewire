<?php

use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\Search\SearchService;
use Illuminate\Support\Facades\DB;

it('clamps a search term over 255 characters before it reaches the LIKE query', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    $bindings = [];
    DB::listen(function ($query) use (&$bindings) {
        foreach ($query->bindings as $binding) {
            if (is_string($binding) && str_contains($binding, 'aaaa')) {
                $bindings[] = $binding;
            }
        }
    });

    app(SearchService::class)->search($actor, str_repeat('a', 500));

    expect($bindings)->not->toBeEmpty();

    foreach ($bindings as $binding) {
        // Bound as "%{$term}%", so the clamped term itself is 2 chars shorter.
        expect(mb_strlen($binding) - 2)->toBeLessThanOrEqual(255);
    }
});

it('clamps a search term to exactly 255 characters, matching a name only reachable at that exact length', function () {
    Permission::findOrCreate('admin.users.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.users.index');

    // Only findable if the 500-char term below is truncated to precisely
    // 255 chars before being bound into the LIKE query: a longer clamp (or
    // no clamp at all) can never match this 255-char name as a substring.
    $exactlyClampedName = str_repeat('a', 255);
    User::factory()->create(['name' => $exactlyClampedName]);

    $results = app(SearchService::class)->search($actor, str_repeat('a', 500));

    $users = $results->firstWhere('module', 'Users');

    expect($users)->not->toBeNull()
        ->and($users['results']->pluck('title'))->toContain($exactlyClampedName);
});
