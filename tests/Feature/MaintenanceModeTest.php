<?php

use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\Setting\SettingService;

it('allows normal access to the dashboard when maintenance mode is off', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.dashboard.index'))->assertOk();
});

it('blocks a regular user from the dashboard when maintenance mode is on', function () {
    app(SettingService::class)->set('maintenance_mode', true);

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.dashboard.index'))->assertStatus(503);
});

it('still allows an actor who can edit settings through while maintenance mode is on', function () {
    Permission::findOrCreate('admin.settings.edit');
    app(SettingService::class)->set('maintenance_mode', true);

    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.settings.edit');

    $this->actingAs($actor)->get(route('admin.dashboard.index'))->assertOk();
});
