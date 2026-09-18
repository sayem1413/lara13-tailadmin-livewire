<?php

use App\Livewire\Admin\ActivityLog\ActivityLogIndex;
use App\Models\Permission\Permission;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Facades\Activity;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.activity-log.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.activity-log.index permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.activity-log.index'))->assertForbidden();
});

it('lists recorded activity for a user with the admin.activity-log.index permission', function () {
    Permission::findOrCreate('admin.activity-log.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.activity-log.index');

    $subject = User::factory()->create(['name' => 'Logged Subject']);
    Activity::performedOn($subject)->causedBy($actor)->log('Created the user');

    $this->actingAs($actor)->get(route('admin.activity-log.index'))
        ->assertOk()
        ->assertSee('Created the user');
});

it('filters activity by event', function () {
    Permission::findOrCreate('admin.activity-log.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.activity-log.index');
    $subject = User::factory()->create();

    Activity::performedOn($subject)->causedBy($actor)->event('custom-event')->log('A custom event happened');
    Activity::performedOn($subject)->causedBy($actor)->event('another-event')->log('Another event happened');

    Livewire::actingAs($actor)
        ->test(ActivityLogIndex::class)
        ->set('event', 'custom-event')
        ->assertSee('A custom event happened')
        ->assertDontSee('Another event happened');
});

it('filters activity by subject type', function () {
    Permission::findOrCreate('admin.activity-log.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.activity-log.index');
    $subject = User::factory()->create();

    Activity::performedOn($subject)->causedBy($actor)->log('Logged against a user');
    Activity::causedBy($actor)->log('Logged with no subject');

    Livewire::actingAs($actor)
        ->test(ActivityLogIndex::class)
        ->set('subjectType', User::class)
        ->assertSee('Logged against a user')
        ->assertDontSee('Logged with no subject');
});

it('filters activity by search term matching the description or causer name', function () {
    Permission::findOrCreate('admin.activity-log.index');
    $actor = User::factory()->create(['name' => 'Searchable Actor']);
    $actor->givePermissionTo('admin.activity-log.index');
    $other = User::factory()->create(['name' => 'Other Actor']);
    $subject = User::factory()->create();

    Activity::performedOn($subject)->causedBy($actor)->log('Matches by description');
    Activity::performedOn($subject)->causedBy($other)->log('Unrelated entry');

    Livewire::actingAs($actor)
        ->test(ActivityLogIndex::class)
        ->set('search', 'Searchable Actor')
        ->assertSee('Matches by description')
        ->assertDontSee('Unrelated entry');
});
