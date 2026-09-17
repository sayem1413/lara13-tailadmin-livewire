<?php

use App\Livewire\Layout\NotificationsDropdown;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('lists the user\'s notifications on the notifications page', function () {
    $user = User::factory()->create();
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['message' => 'Something happened'],
        'read_at' => null,
    ]);

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Something happened');
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $id = (string) Str::uuid();
    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['message' => 'Something happened'],
        'read_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(NotificationsDropdown::class)
        ->call('markAsRead', $id);

    expect($user->notifications()->whereKey($id)->first()->read_at)->not->toBeNull();
});

it('marks every notification as read', function () {
    $user = User::factory()->create();

    foreach (range(1, 2) as $i) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => ['message' => "Notification {$i}"],
            'read_at' => null,
        ]);
    }

    Livewire::actingAs($user)
        ->test(NotificationsDropdown::class)
        ->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});
