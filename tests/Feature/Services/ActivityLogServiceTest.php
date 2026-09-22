<?php

use App\Models\Permission\Permission;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\Activity;

it('clamps a search term over 255 characters before it reaches the LIKE query', function () {
    $subject = User::factory()->create();
    $actor = User::factory()->create();
    Activity::performedOn($subject)->causedBy($actor)->log('Ordinary description');

    $bindings = [];
    DB::listen(function ($query) use (&$bindings) {
        foreach ($query->bindings as $binding) {
            if (is_string($binding) && str_contains($binding, 'aaaa')) {
                $bindings[] = $binding;
            }
        }
    });

    app(ActivityLogService::class)->paginate(search: str_repeat('a', 500));

    expect($bindings)->not->toBeEmpty();

    foreach ($bindings as $binding) {
        // Bound as "%{$search}%", so the clamped term itself is 2 chars shorter.
        expect(mb_strlen($binding) - 2)->toBeLessThanOrEqual(255);
    }
});

it('clamps a search term to exactly 255 characters, matching a description only reachable at that exact length', function () {
    Permission::findOrCreate('admin.activity-log.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.activity-log.index');
    $subject = User::factory()->create();

    // Only findable if the 500-char search below is truncated to precisely
    // 255 chars before being bound into the LIKE query: a longer clamp (or
    // no clamp at all) can never match this 255-char description as a
    // substring.
    $exactlyClampedDescription = str_repeat('a', 255);
    Activity::performedOn($subject)->causedBy($actor)->log($exactlyClampedDescription);

    $activities = app(ActivityLogService::class)->paginate(search: str_repeat('a', 500));

    expect($activities->pluck('description'))->toContain($exactlyClampedDescription);
});
