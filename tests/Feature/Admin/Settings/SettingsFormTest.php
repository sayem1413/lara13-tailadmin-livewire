<?php

use App\Livewire\Admin\Settings\SettingsForm;
use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\SettingService;
use Livewire\Livewire;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.settings.edit permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
});

it('renders configured fields using their configured defaults', function () {
    Permission::findOrCreate('admin.settings.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.settings.edit');

    $this->actingAs($actor)->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Application Name')
        ->assertSee(config('app.name'));
});

it('forbids saving without the admin.settings.update permission', function () {
    Permission::findOrCreate('admin.settings.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.settings.edit');

    Livewire::actingAs($actor)
        ->test(SettingsForm::class)
        ->call('save')
        ->assertForbidden();
});

it('persists submitted values through the setting service', function () {
    Permission::findOrCreate('admin.settings.edit');
    Permission::findOrCreate('admin.settings.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.settings.edit', 'admin.settings.update']);

    Livewire::actingAs($actor)
        ->test(SettingsForm::class)
        ->set('values.app_name', 'New App Name')
        ->set('values.support_email', 'support@example.com')
        ->set('values.maintenance_mode', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    $settings = app(SettingService::class);

    expect($settings->get('app_name'))->toBe('New App Name')
        ->and($settings->get('support_email'))->toBe('support@example.com')
        ->and($settings->get('maintenance_mode'))->toBeTrue();
});

it('rejects a blank app_name and leaves the currently saved value untouched', function () {
    Permission::findOrCreate('admin.settings.edit');
    Permission::findOrCreate('admin.settings.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['admin.settings.edit', 'admin.settings.update']);

    app(SettingService::class)->set('app_name', 'Existing Name');

    Livewire::actingAs($actor)
        ->test(SettingsForm::class)
        ->set('values.app_name', '')
        ->call('save')
        ->assertHasErrors(['values.app_name' => 'required']);

    expect(app(SettingService::class)->get('app_name'))->toBe('Existing Name');
});
