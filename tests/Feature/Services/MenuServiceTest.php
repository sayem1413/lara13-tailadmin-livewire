<?php

use App\Models\User;
use App\Services\MenuService;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * Registers a throwaway named route and forces the router to re-index its
 * name lookup table immediately: Laravel only does that sweep once, right
 * after routes/web.php finishes loading, so a route added after boot (as
 * these fixture routes are) needs it triggered manually for Route::has()
 * to see it.
 */
function registerMenuTestRoute(string $name): void
{
    Route::get('/'.str_replace('.', '-', $name), fn () => 'ok')->name($name);
    app('router')->getRoutes()->refreshNameLookups();
}

it('hides an item whose named route is not registered', function () {
    config(['menu' => [
        ['group' => 'MAIN', 'items' => [
            ['label' => 'Ghost', 'route' => 'nowhere.route', 'permission' => null, 'order' => 1],
        ]],
    ]]);

    $this->actingAs(User::factory()->create());

    expect(app(MenuService::class)->forSidebar())->toBeEmpty();
});

it('keeps a group but drops only its hidden items when some items are visible', function () {
    registerMenuTestRoute('menu-test.visible');

    config(['menu' => [
        ['group' => 'MAIN', 'items' => [
            ['label' => 'Visible', 'route' => 'menu-test.visible', 'permission' => null, 'order' => 1],
            ['label' => 'Missing', 'route' => 'menu-test.missing', 'permission' => null, 'order' => 2],
        ]],
    ]]);

    $this->actingAs(User::factory()->create());

    $menu = app(MenuService::class)->forSidebar();

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['items']->pluck('label')->all())->toBe(['Visible']);
});

it('hides an item that requires a permission the user lacks', function () {
    registerMenuTestRoute('menu-test.restricted');
    Permission::findOrCreate('menu-test.view');

    config(['menu' => [
        ['group' => 'MAIN', 'items' => [
            ['label' => 'Restricted', 'route' => 'menu-test.restricted', 'permission' => 'menu-test.view', 'order' => 1],
        ]],
    ]]);

    $this->actingAs(User::factory()->create());

    expect(app(MenuService::class)->forSidebar())->toBeEmpty();
});

it('shows an item once the user is granted its required permission', function () {
    registerMenuTestRoute('menu-test.allowed');
    Permission::findOrCreate('menu-test.view');

    config(['menu' => [
        ['group' => 'MAIN', 'items' => [
            ['label' => 'Allowed', 'route' => 'menu-test.allowed', 'permission' => 'menu-test.view', 'order' => 1],
        ]],
    ]]);

    $user = User::factory()->create();
    $user->givePermissionTo('menu-test.view');
    $this->actingAs($user);

    $menu = app(MenuService::class)->forSidebar();

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['items']->pluck('label')->all())->toBe(['Allowed']);
});

it('shows a parent item only while it still has at least one visible child', function () {
    registerMenuTestRoute('menu-test.child');

    config(['menu' => [
        ['group' => 'MAIN', 'items' => [
            ['label' => 'Parent', 'order' => 1, 'children' => [
                ['label' => 'Child A', 'route' => 'menu-test.child', 'permission' => null, 'order' => 1],
                ['label' => 'Child B', 'route' => 'menu-test.missing-child', 'permission' => null, 'order' => 2],
            ]],
        ]],
    ]]);

    $this->actingAs(User::factory()->create());

    $menu = app(MenuService::class)->forSidebar();

    expect($menu[0]['items'][0]['children'])->toHaveCount(1)
        ->and($menu[0]['items'][0]['children'][0]['label'])->toBe('Child A');
});
