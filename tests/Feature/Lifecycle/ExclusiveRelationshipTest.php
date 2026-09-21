<?php

use App\Enums\LifecycleStatus;
use App\Exceptions\Lifecycle\ParentNotActiveException;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoDepartment;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoOrganization;

beforeEach(function () {
    LifecycleDemoOrganization::$departmentRestoreStrategy = 'pending_activation';
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
