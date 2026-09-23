<?php

use App\Models\Permission\Permission;
use App\Models\User;
use App\Repositories\Interfaces\Setting\SettingRepositoryInterface;
use App\Services\Setting\SettingService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

it('shows a friendly error and logs the failure instead of a raw 500 when persisting settings throws', function () {
    Permission::findOrCreate('admin.settings.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.settings.update');

    $this->mock(SettingRepositoryInterface::class, function ($mock) {
        $mock->shouldReceive('all')->andReturn([]);
        $mock->shouldReceive('set')->andThrow(new RuntimeException('DB gone away'));
    });

    Log::spy();

    $this->actingAs($actor)
        ->put(route('admin.settings.update'), ['values' => ['app_name' => 'New App Name']])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('error');

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context) => $message === 'Failed to save settings.' && $context['exception'] instanceof RuntimeException
    );
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
