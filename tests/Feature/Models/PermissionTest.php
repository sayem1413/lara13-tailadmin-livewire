<?php

use App\Models\Permission\Permission;

it('expands nothing when no permissions are selected', function () {
    expect(Permission::expandWithImplied([]))->toBe([]);
});

it("implies a module's index permission when one of its write permissions is selected", function () {
    $index = Permission::create(['name' => 'admin.widgets.index', 'guard_name' => 'web', 'module' => 'widgets', 'section' => 'index']);
    $create = Permission::create(['name' => 'admin.widgets.create', 'guard_name' => 'web', 'module' => 'widgets', 'section' => 'create']);

    $expanded = Permission::expandWithImplied([$create->id]);

    expect($expanded)->toContain($create->id)
        ->and($expanded)->toContain($index->id);
});

it('does not imply permissions belonging to a different module', function () {
    $usersIndex = Permission::create(['name' => 'admin.users.index', 'guard_name' => 'web', 'module' => 'users', 'section' => 'index']);
    $rolesCreate = Permission::create(['name' => 'admin.roles.create', 'guard_name' => 'web', 'module' => 'roles', 'section' => 'create']);

    $expanded = Permission::expandWithImplied([$rolesCreate->id]);

    expect($expanded)->toContain($rolesCreate->id)
        ->and($expanded)->not->toContain($usersIndex->id);
});

it('leaves a view-only selection unchanged', function () {
    $index = Permission::create(['name' => 'admin.widgets.index', 'guard_name' => 'web', 'module' => 'widgets', 'section' => 'index']);

    expect(Permission::expandWithImplied([$index->id]))->toBe([$index->id]);
});

it('does not imply anything for a permission with no module or section', function () {
    $adHoc = Permission::create(['name' => 'some.permission', 'guard_name' => 'web']);

    expect(Permission::expandWithImplied([$adHoc->id]))->toBe([$adHoc->id]);
});
