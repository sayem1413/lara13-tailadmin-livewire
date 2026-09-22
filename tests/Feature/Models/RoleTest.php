<?php

use App\Models\Permission\Role;
use Spatie\Activitylog\Models\Activity;

it('logs the configured fields when a role is created', function () {
    $role = Role::create([
        'name' => 'Editor',
        'guard_name' => 'web',
        'description' => 'Edits content',
        'is_active' => true,
    ]);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($role->id)
        ->and($activity->subject_type)->toBe(Role::class)
        ->and($activity->event)->toBe('created')
        ->and($activity->attribute_changes['attributes'])->toBe([
            'name' => 'Editor',
            'guard_name' => 'web',
            'description' => 'Edits content',
            'is_active' => true,
        ]);
});

it('logs only the dirty configured fields when a role is updated', function () {
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);

    $role->update(['description' => 'Updated description']);

    $activity = Activity::query()->latest('id')->first();

    expect($activity->event)->toBe('updated')
        ->and($activity->attribute_changes['attributes'])->toBe(['description' => 'Updated description'])
        ->and($activity->attribute_changes['old'])->toBe(['description' => null]);
});

it('does not log a save that leaves every configured field unchanged', function () {
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);

    $countBefore = Activity::query()->count();

    $role->update(['name' => 'Editor']);

    expect(Activity::query()->count())->toBe($countBefore);
});
