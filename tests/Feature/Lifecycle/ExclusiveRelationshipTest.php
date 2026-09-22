<?php

use App\Enums\LifecycleStatus;
use App\Exceptions\Lifecycle\ParentNotActiveException;
use App\Exceptions\Lifecycle\RetainedRecordException;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoDepartment;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoOrganization;

beforeEach(function () {
    LifecycleDemoOrganization::$departmentRestoreStrategy = 'pending_activation';
    LifecycleDemoOrganization::$departmentCascade = ['deactivate', 'delete'];
    LifecycleDemoOrganization::$departmentsRetained = false;
    LifecycleDemoDepartment::$retainedFromOrganization = false;
});

it('cascades deactivation from a parent to all its exclusive children', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $departmentA = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);
    $departmentB = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Support']);

    lifecycleService()->deactivate($organization);

    expect($departmentA->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive)
        ->and($departmentB->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);
});

it('cascades a soft delete from a parent to all its exclusive children', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->delete($organization);

    expect(LifecycleDemoDepartment::find($department->id))->toBeNull()
        ->and(LifecycleDemoDepartment::withTrashed()->findOrFail($department->id)->trashed())->toBeTrue();
});

it('cascades a force delete from a parent to all its exclusive children', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->forceDelete($organization);

    expect(LifecycleDemoDepartment::withTrashed()->find($department->id))->toBeNull();
});

it('restores children to pending_activation by default, not silently reactivating them', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->delete($organization);
    lifecycleService()->restore($organization);

    $department->refresh();

    expect($department->trashed())->toBeFalse()
        ->and($department->lifecycle_status)->toBe(LifecycleStatus::PendingActivation);
});

it('cascade-restores and reactivates children when the relationship opts into the cascade restore strategy', function () {
    LifecycleDemoOrganization::$departmentRestoreStrategy = 'cascade';

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->delete($organization);
    lifecycleService()->restore($organization);

    $department->refresh();

    expect($department->trashed())->toBeFalse()
        ->and($department->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('blocks activating a child directly while its parent is inactive', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme', 'lifecycle_status' => 'inactive']);
    $department = LifecycleDemoDepartment::create([
        'lifecycle_demo_organization_id' => $organization->id,
        'name' => 'Sales',
        'lifecycle_status' => 'inactive',
    ]);

    expect(fn () => lifecycleService()->activate($department))
        ->toThrow(ParentNotActiveException::class);

    expect($department->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);
});

it('allows activating a child directly while its parent is active', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create([
        'lifecycle_demo_organization_id' => $organization->id,
        'name' => 'Sales',
        'lifecycle_status' => 'inactive',
    ]);

    lifecycleService()->activate($department);

    expect($department->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('blocks restoring a child directly while its parent is inactive', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);
    $department->delete();

    $organization->deactivate();

    expect(fn () => lifecycleService()->restore($department))
        ->toThrow(ParentNotActiveException::class);

    expect($department->refresh()->trashed())->toBeTrue();
});

it('does not cascade activation to children by default', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create([
        'lifecycle_demo_organization_id' => $organization->id,
        'name' => 'Sales',
        'lifecycle_status' => 'inactive',
    ]);

    lifecycleService()->deactivate($organization);
    lifecycleService()->activate($organization);

    expect($department->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);
});

it('cascades activation to children when the relationship opts into the activate cascade trigger', function () {
    LifecycleDemoOrganization::$departmentCascade = ['activate', 'deactivate', 'delete'];

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->deactivate($organization);
    lifecycleService()->activate($organization);

    expect($department->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('writes an activity log entry when activating, matching every other lifecycle action', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme', 'lifecycle_status' => 'inactive']);

    lifecycleService()->activate($organization);

    expect(Activity::latest('id')->first()->description)->toBe('Activated LifecycleDemoOrganization');
});

it('blocks force-deleting a record that declares itself retained on its own upward rule', function () {
    LifecycleDemoDepartment::$retainedFromOrganization = true;

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    expect(fn () => lifecycleService()->forceDelete($department))
        ->toThrow(RetainedRecordException::class);

    expect(LifecycleDemoDepartment::withTrashed()->find($department->id))->not->toBeNull();
});

it('blocks a parent force-delete cascade from reaching a relationship marked retain', function () {
    LifecycleDemoOrganization::$departmentsRetained = true;

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    expect(fn () => lifecycleService()->forceDelete($organization))
        ->toThrow(RetainedRecordException::class);

    // The whole triggering transaction rolled back - the organization
    // itself must still exist too, not just the retained department.
    expect(LifecycleDemoOrganization::withTrashed()->find($organization->id))->not->toBeNull()
        ->and(LifecycleDemoDepartment::withTrashed()->find($department->id))->not->toBeNull();
});

it('allows a parent force-delete cascade through a relationship marked retain once it has no remaining rows', function () {
    LifecycleDemoOrganization::$departmentsRetained = true;

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);

    lifecycleService()->forceDelete($organization);

    expect(LifecycleDemoOrganization::withTrashed()->find($organization->id))->toBeNull();
});

it('does not block a soft delete cascade through a relationship marked retain - retain only guards permanent deletion', function () {
    LifecycleDemoOrganization::$departmentsRetained = true;

    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->delete($organization);

    expect($department->refresh()->trashed())->toBeTrue();
});

it('does not re-cascade when deactivating an already-inactive parent', function () {
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);

    lifecycleService()->deactivate($organization);

    // Bypasses the model entirely (no guard, no cascade) so the only way
    // this can be 'active' afterward is if the second deactivate() call
    // below incorrectly re-cascaded.
    LifecycleDemoDepartment::whereKey($department->id)->update(['lifecycle_status' => 'active']);

    lifecycleService()->deactivate($organization);

    expect($department->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});
