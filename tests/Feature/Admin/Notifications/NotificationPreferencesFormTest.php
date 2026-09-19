<?php

use App\Livewire\Admin\Notifications\NotificationPreferencesForm;
use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Livewire\Livewire;

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
