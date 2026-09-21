<?php

use App\Enums\LifecycleStatus;
use App\Events\Lifecycle\Deactivating;
use App\Jobs\Lifecycle\CascadeLifecycleActionJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoDepartment;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoEmployee;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoOrganization;

beforeEach(function () {
    LifecycleDemoOrganization::$departmentRestoreStrategy = 'pending_activation';
    LifecycleDemoOrganization::$departmentCascade = ['deactivate', 'delete'];
    LifecycleDemoDepartment::$employeeCascade = ['deactivate', 'delete'];
    config(['lifecycle.bulk_threshold' => 500]);
});

it('re-reads the row being deactivated with a lock before cascading', function () {
    // SQLite (this suite's test driver) compiles lockForUpdate() to an
    // empty clause - it has no real row-locking model - so this can
    // only confirm the locked re-read actually happens (the code path
    // this module's concurrency-safety depends on), not that a real
    // lock is held. That needs a real MySQL/Postgres connection.
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);

    $selects = 0;
    DB::listen(function ($query) use (&$selects): void {
        if (str_contains($query->sql, 'lifecycle_demo_organizations') && str_starts_with(trim($query->sql), 'select')) {
            $selects++;
        }
    });

    lifecycleService()->deactivate($organization);

    // Eloquent's own save() on an already-loaded model issues no SELECT
    // at all - the only way one appears here is lockRow()'s locked
    // re-read.
    expect($selects)->toBeGreaterThanOrEqual(1);
});

it('rolls back the entire cascade when one child fails mid-cascade', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $departmentA = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);
    $departmentB = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Support']);

    Event::listen(Deactivating::class, function (Deactivating $event) use ($departmentB): void {
        if ($event->model->is($departmentB)) {
            throw new RuntimeException('Simulated mid-cascade failure');
        }
    });

    expect(fn () => lifecycleService()->deactivate($organization))
        ->toThrow(RuntimeException::class);

    expect($organization->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($departmentA->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($departmentB->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('is idempotent when deactivating an already-inactive record - no duplicate cascade or log entry', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->deactivate($organization);
    $countAfterFirst = Activity::count();

    lifecycleService()->deactivate($organization);

    expect(Activity::count())->toBe($countAfterFirst);
});

it('writes one grouped activity log entry per cascade with a per-type affected breakdown', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Support']);

    lifecycleService()->deactivate($organization);

    $activity = Activity::latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Deactivated')
        ->and($activity->description)->toContain('2 LifecycleDemoDepartment')
        ->and($activity->properties->get('affected'))->toBe(['LifecycleDemoDepartment' => 2]);
});

it('dispatches a queued cascade job instead of cascading inline once a relationship crosses the bulk threshold', function () {
    config(['lifecycle.bulk_threshold' => 2]);
    Queue::fake();

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'A']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'B']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'C']);

    lifecycleService()->deactivate($organization);

    Queue::assertPushed(CascadeLifecycleActionJob::class, fn (CascadeLifecycleActionJob $job): bool => $job->modelClass === LifecycleDemoOrganization::class
        && $job->action === 'deactivate'
        && $job->relationName === 'departments');
});

it('applies the queued cascade job\'s action to every child in the relation', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $departmentA = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'A']);
    $departmentB = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'B']);

    (new CascadeLifecycleActionJob(LifecycleDemoOrganization::class, $organization->id, 'departments', 'deactivate'))->handle();

    expect($departmentA->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive)
        ->and($departmentB->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);
});

it('applies the queued cascade job\'s activate action to every inactive child in the relation', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $departmentA = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'A', 'lifecycle_status' => 'inactive']);
    $departmentB = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'B', 'lifecycle_status' => 'inactive']);

    (new CascadeLifecycleActionJob(LifecycleDemoOrganization::class, $organization->id, 'departments', 'activate'))->handle();

    expect($departmentA->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($departmentB->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('describes a queued (not-yet-completed) relation separately from an already-cascaded one, never claiming it already cascaded', function () {
    config(['lifecycle.bulk_threshold' => 2]);
    Queue::fake();

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'A']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'B']);
    LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'C']);

    lifecycleService()->deactivate($organization);

    $activity = Activity::latest('id')->first();

    expect($activity->description)->toContain('queued 3 LifecycleDemoDepartment for background processing')
        ->and($activity->description)->not->toContain('cascaded to')
        ->and($activity->description)->not->toContain('queued:')
        ->and($activity->properties->get('affected'))->toBe(['queued:LifecycleDemoDepartment' => 3]);
});

it('writes both a "cascaded to" and a "queued" clause on the same log entry when a nested relationship crosses the threshold but the direct one does not', function () {
    // Threshold set so the 1 department stays inline, but its 2
    // employees cross it - both counts land in the SAME top-level
    // Activity entry for the organization's own deactivate(), since the
    // department's own (recursive) cascade shares this service's single
    // $affectedCounts accumulator for the whole triggering action.
    config(['lifecycle.bulk_threshold' => 2]);
    Queue::fake();

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);
    LifecycleDemoEmployee::create(['lifecycle_demo_department_id' => $department->id, 'name' => 'Jane']);
    LifecycleDemoEmployee::create(['lifecycle_demo_department_id' => $department->id, 'name' => 'Joe']);

    lifecycleService()->deactivate($organization);

    $activity = Activity::latest('id')->first();

    expect($activity->description)->toContain('cascaded to 1 LifecycleDemoDepartment')
        ->and($activity->description)->toContain('queued 2 LifecycleDemoEmployee for background processing')
        ->and($activity->description)->toContain('; ');
});

it('writes a Log::error() and a drill-down activity log entry when a queued cascade job exhausts its retries', function () {
    Log::spy();

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);

    $job = new CascadeLifecycleActionJob(LifecycleDemoOrganization::class, $organization->id, 'departments', 'deactivate');
    $job->failed(new RuntimeException('Simulated permanent queue failure'));

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'Queued lifecycle cascade failed.'
            && $context['model'] === LifecycleDemoOrganization::class
            && $context['relation'] === 'departments'
            && $context['action'] === 'deactivate'
    );

    $activity = Activity::latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('did not complete')
        ->and($activity->subject_id)->toBe($organization->id);
});
