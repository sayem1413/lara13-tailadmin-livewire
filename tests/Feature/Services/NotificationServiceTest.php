<?php

use App\Models\Permission\Role;
use App\Models\User;
use App\Notifications\UserRoleUpdatedNotification;
use App\Services\Notification\NotificationService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

it('does not let a failed notification dispatch throw or roll back a surrounding transaction', function () {
    $user = User::factory()->create();

    $notification = new class(['Editor']) extends UserRoleUpdatedNotification
    {
        public function toDatabase(object $notifiable): array
        {
            throw new RuntimeException('Simulated notification delivery failure.');
        }
    };

    DB::transaction(function () use ($user, $notification) {
        $user->forceFill(['name' => 'Updated During Transaction'])->save();

        app(NotificationService::class)->send($user, $notification);
    });

    expect($user->fresh()->name)->toBe('Updated During Transaction');
});

it('keeps a role change committed and logs the failure when UserService::updateUser() dispatches a notification that throws', function () {
    Role::findOrCreate('Editor');

    $target = User::factory()->create();

    // Forces the real dispatch call made by NotificationService::send()'s
    // $user->notify(...) (which resolves the same ChannelManager singleton
    // the Notification facade proxies) to throw, without needing a
    // custom Notification subclass - this exercises the actual
    // UserRoleUpdatedNotification UserService::updateUser() sends.
    Notification::shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Simulated notification transport failure.'));

    Log::spy();

    app(UserService::class)->updateUser($target, [
        'name' => $target->name,
        'email' => $target->email,
        'roles' => ['Editor'],
    ]);

    // The role sync happened inside the same DB::transaction() as the
    // notification dispatch - proving it committed despite the thrown
    // exception shows the transaction was not rolled back.
    expect($target->fresh()->hasRole('Editor'))->toBeTrue();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($target) {
            return $message === 'Failed to dispatch notification.'
                && $context['notification'] === UserRoleUpdatedNotification::class
                && $context['user_id'] === $target->id
                && $context['exception'] instanceof RuntimeException;
        });
});
