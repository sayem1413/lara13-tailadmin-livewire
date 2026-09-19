<?php

use App\Models\User;
use App\Notifications\UserRoleUpdatedNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Notification;

it('resolves every configured channel enabled by default when no preference is stored', function () {
    $user = User::factory()->create();

    expect(app(NotificationService::class)->channelsFor($user, 'account_security'))
        ->toEqualCanonicalizing(['database', 'mail']);
});

it('excludes a channel the user has disabled', function () {
    $user = User::factory()->create();

    app(NotificationService::class)->updatePreferences($user, [
        'account_security' => ['mail' => false, 'database' => true],
    ]);

    expect(app(NotificationService::class)->channelsFor($user, 'account_security'))
        ->toBe(['database']);
});

it('sends a notification only through the channels the user has enabled', function () {
    Notification::fake();

    $user = User::factory()->create();

    app(NotificationService::class)->updatePreferences($user, [
        'account_security' => ['mail' => false, 'database' => true],
    ]);

    app(NotificationService::class)->send($user, new UserRoleUpdatedNotification(['Editor']));

    Notification::assertSentTo($user, UserRoleUpdatedNotification::class, function ($notification, $channels) {
        return $channels === ['database'];
    });
});

it('sends nothing when every channel for the type is disabled', function () {
    Notification::fake();

    $user = User::factory()->create();

    app(NotificationService::class)->updatePreferences($user, [
        'account_security' => ['mail' => false, 'database' => false],
    ]);

    app(NotificationService::class)->send($user, new UserRoleUpdatedNotification(['Editor']));

    Notification::assertNotSentTo($user, UserRoleUpdatedNotification::class);
});
