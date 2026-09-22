<?php

use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\SettingService;

it('forbids updating settings without the admin.settings.update permission', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('admin.settings.update'), [
            'values' => ['app_name' => 'New Name', 'tax_rate' => '15', 'low_stock_threshold' => '10'],
        ])
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
                'tax_rate' => '15',
                'low_stock_threshold' => '10',
            ],
        ])
        ->assertRedirect(route('admin.settings.edit'));

    $settings = app(SettingService::class);

    expect($settings->get('app_name'))->toBe('New App Name')
        ->and($settings->get('support_email'))->toBe('support@example.com')
        ->and($settings->get('maintenance_mode'))->toBeTrue();
});

it('rejects a blank app_name and leaves the currently saved value untouched', function () {
    Permission::findOrCreate('admin.settings.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.settings.update');

    app(SettingService::class)->set('app_name', 'Existing Name');

    $this->actingAs($actor)
        ->put(route('admin.settings.update'), ['values' => ['app_name' => '']])
        ->assertSessionHasErrors('values.app_name');

    expect(app(SettingService::class)->get('app_name'))->toBe('Existing Name');
});
