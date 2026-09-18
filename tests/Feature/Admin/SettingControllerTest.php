<?php

use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\SettingService;

it('forbids updating settings without the admin.settings.update permission', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('admin.settings.update'), ['values' => ['app_name' => 'New Name']])
        ->assertForbidden();
});

it('persists submitted values through the setting service', function () {
    Permission::findOrCreate('admin.settings.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.settings.update');

    $this->actingAs($actor)
        ->put(route('admin.settings.update'), [
            'values' => [
                'app_name' => 'New App Name',
                'support_email' => 'support@example.com',
                'maintenance_mode' => true,
            ],
        ])
        ->assertRedirect(route('admin.settings.edit'));

    $settings = app(SettingService::class);

    expect($settings->get('app_name'))->toBe('New App Name')
        ->and($settings->get('support_email'))->toBe('support@example.com')
        ->and($settings->get('maintenance_mode'))->toBeTrue();
});
