<?php

use App\Enums\LifecycleStatus;
use App\Exceptions\Lifecycle\ParentNotActiveException;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoDepartment;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoEmployee;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoOrganization;

beforeEach(function () {
    LifecycleDemoOrganization::$departmentRestoreStrategy = 'pending_activation';
    LifecycleDemoDepartment::$employeeRestoreStrategy = 'pending_activation';
});

function makeHierarchy(): array
{
    $organization = LifecycleDemoOrganization::create(['name' => 'Acme']);
    $department = LifecycleDemoDepartment::create(['lifecycle_demo_organization_id' => $organization->id, 'name' => 'Sales']);
    $employee = LifecycleDemoEmployee::create(['lifecycle_demo_department_id' => $department->id, 'name' => 'Jane']);

    return [$organization, $department, $employee];
}

it('recursively cascades deactivation down a three-level chain', function () {
    [$organization, $department, $employee] = makeHierarchy();

    lifecycleService()->deactivate($organization);

    expect($department->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive)
        ->and($employee->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);
});

it('recursively cascades a soft delete down a three-level chain', function () {
    [$organization, $department, $employee] = makeHierarchy();

    lifecycleService()->delete($organization);

    expect($department->refresh()->trashed())->toBeTrue()
        ->and($employee->refresh()->trashed())->toBeTrue();
});

it('blocks activating a grandchild directly while its immediate parent is inactive', function () {
    [$organization, $department, $employee] = makeHierarchy();
    $department->deactivate();
    $employee->deactivate();

    expect(fn () => lifecycleService()->activate($employee))
        ->toThrow(ParentNotActiveException::class);
});

it('blocks activating a grandchild directly while only the top-level ancestor is inactive', function () {
    [$organization, $department, $employee] = makeHierarchy();
    $organization->deactivate();
    // Direct-column update so the department's own status stays 'active'
    // despite its parent being inactive - isolates the check to the
    // grandparent link specifically, not a cascade from department.
    LifecycleDemoDepartment::whereKey($department->id)->update(['lifecycle_status' => 'active']);
    $employee->deactivate();

    expect(fn () => lifecycleService()->activate($employee))
        ->toThrow(ParentNotActiveException::class);
});

it('allows activating a grandchild directly when every ancestor is active', function () {
    [$organization, $department, $employee] = makeHierarchy();
    $employee->deactivate();

    lifecycleService()->activate($employee);

    expect($employee->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('blocks restoring a grandchild while only the top-level ancestor is inactive, not just the immediate parent', function () {
    [$organization, $department, $employee] = makeHierarchy();
    $employee->delete();
    $organization->deactivate();
    LifecycleDemoDepartment::whereKey($department->id)->update(['lifecycle_status' => 'active']);

    expect(fn () => lifecycleService()->restore($employee))
        ->toThrow(ParentNotActiveException::class);

    expect($employee->refresh()->trashed())->toBeTrue();
});

it('requires every ancestor to be active before a grandchild can be restored', function () {
    [$organization, $department, $employee] = makeHierarchy();
    $employee->delete();

    lifecycleService()->restore($employee);

    expect($employee->refresh()->trashed())->toBeFalse();
});
