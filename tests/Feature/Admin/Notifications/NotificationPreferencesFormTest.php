<?php

use App\Livewire\Admin\Notifications\NotificationPreferencesForm;
use App\Models\NotificationPreference;
use App\Models\Permission\Permission;
use App\Models\User;
use App\Repositories\Interfaces\Notification\NotificationPreferenceRepositoryInterface;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use RuntimeException;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.notifications.preferences.edit'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.notifications.preferences.edit permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.notifications.preferences.edit'))->assertForbidden();
});

it('renders every configured notification type using its default channel state', function () {
    Permission::findOrCreate('admin.notifications.preferences.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.notifications.preferences.edit');

    $this->actingAs($actor)->get(route('admin.notifications.preferences.edit'))
        ->assertOk()
        ->assertSee('Account & Security');
});

it('persists submitted preferences through the notification service', function () {
    Permission::findOrCreate('admin.notifications.preferences.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.notifications.preferences.edit');

    Livewire::actingAs($actor)
        ->test(NotificationPreferencesForm::class)
        ->set('values.account_security.mail', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'success';
        });

    expect(app(NotificationService::class)->channelsFor($actor, 'account_security'))
        ->toBe(['database']);
});

it('shows a friendly error and logs the failure instead of crashing when persisting preferences throws', function () {
    Permission::findOrCreate('admin.notifications.preferences.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.notifications.preferences.edit');

    $this->mock(NotificationPreferenceRepositoryInterface::class, function ($mock) {
        $mock->shouldReceive('forUser')->andReturn(collect());
        $mock->shouldReceive('setMany')->andThrow(new RuntimeException('DB gone away'));
    });

    Log::spy();

    Livewire::actingAs($actor)
        ->test(NotificationPreferencesForm::class)
        ->set('values.account_security.mail', false)
        ->call('save')
        ->assertDispatched('toast', function (string $name, array $params) {
            return $params['type'] === 'error';
        });

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context) => $message === 'Failed to save notification preferences.' && $context['exception'] instanceof RuntimeException
    );
});

it('ignores a type/channel pair injected outside the configured notification_types schema', function () {
    Permission::findOrCreate('admin.notifications.preferences.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.notifications.preferences.edit');

    // Simulates a tampered Livewire request payload smuggling an extra key
    // into the public $values property that was never part of the
    // config-driven schema rendered to the actor.
    Livewire::actingAs($actor)
        ->test(NotificationPreferencesForm::class)
        ->set('values.injected_type.injected_channel', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(NotificationPreference::where('type', 'injected_type')->exists())->toBeFalse();
});

it('strips an injected fake type/channel while still persisting a legitimate change in the same submission', function () {
    Permission::findOrCreate('admin.notifications.preferences.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.notifications.preferences.edit');

    // rules() must be built from config('notification_types'), not from
    // array_keys($this->values) - if it were derived from $this->values
    // (the tamperable, client-hydrated property), the injected key below
    // would pick up its own "boolean" rule from its own presence in
    // $values and validate itself right alongside the legitimate change.
    Livewire::actingAs($actor)
        ->test(NotificationPreferencesForm::class)
        ->set('values.account_security.mail', false)
        ->set('values.injected_type.injected_channel', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(NotificationPreference::where('type', 'injected_type')->exists())->toBeFalse();

    expect(app(NotificationService::class)->channelsFor($actor, 'account_security'))
        ->toBe(['database']);
});
