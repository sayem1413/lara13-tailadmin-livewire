<?php

use App\Enums\DeletionStrategy;
use App\Enums\LifecycleStatus;
use App\Exceptions\Lifecycle\CircularReferenceException;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoFolder;

beforeEach(function () {
    LifecycleDemoFolder::$deletionStrategy = 'delete_subtree';
});

it('recursively cascades deactivation down the entire subtree', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);
    $grandchild = LifecycleDemoFolder::create(['parent_id' => $child->id, 'name' => 'Grandchild']);

    lifecycleService()->deactivate($root);

    expect($child->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive)
        ->and($grandchild->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);
});

it('recursively cascades a soft delete down the entire subtree', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);
    $grandchild = LifecycleDemoFolder::create(['parent_id' => $child->id, 'name' => 'Grandchild']);

    lifecycleService()->delete($root);

    expect($child->refresh()->trashed())->toBeTrue()
        ->and($grandchild->refresh()->trashed())->toBeTrue();
});

it('deletes the entire subtree under the delete_subtree strategy', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);

    lifecycleService()->deleteNode($root, DeletionStrategy::DeleteSubtree);

    expect($child->refresh()->trashed())->toBeTrue();
});

it('promotes direct children to the deleted node\'s own parent under the promote_children strategy', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $middle = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Middle']);
    $leaf = LifecycleDemoFolder::create(['parent_id' => $middle->id, 'name' => 'Leaf']);

    lifecycleService()->deleteNode($middle, DeletionStrategy::PromoteChildren);

    expect($leaf->refresh()->parent_id)->toBe($root->id)
        ->and($leaf->trashed())->toBeFalse()
        ->and(LifecycleDemoFolder::withTrashed()->findOrFail($middle->id)->trashed())->toBeTrue();
});

it('blocks a folder from becoming its own parent', function () {
    $folder = LifecycleDemoFolder::create(['name' => 'Root']);

    expect(fn () => tap($folder)->update(['parent_id' => $folder->id]))
        ->toThrow(CircularReferenceException::class);
});

it('blocks re-parenting a folder to one of its own descendants', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);
    $grandchild = LifecycleDemoFolder::create(['parent_id' => $child->id, 'name' => 'Grandchild']);

    expect(fn () => $root->update(['parent_id' => $grandchild->id]))
        ->toThrow(CircularReferenceException::class);

    expect($root->refresh()->parent_id)->toBeNull();
});

it('allows re-parenting to an unrelated node and keeps the closure table correct for the moved subtree', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);
    $grandchild = LifecycleDemoFolder::create(['parent_id' => $child->id, 'name' => 'Grandchild']);
    $otherRoot = LifecycleDemoFolder::create(['name' => 'Other']);

    $child->update(['parent_id' => $otherRoot->id]);

    // The moved subtree's ancestry now runs through $otherRoot, so
    // $otherRoot becoming a descendant of $grandchild would be a cycle -
    // this only throws if the closure table was actually updated for
    // the whole moved subtree (child AND grandchild), not just $child.
    expect(fn () => $otherRoot->update(['parent_id' => $grandchild->id]))
        ->toThrow(CircularReferenceException::class);

    // And $root, no longer an ancestor of $child/$grandchild, must no
    // longer cascade to them.
    lifecycleService()->deactivate($root->refresh());

    expect($child->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($grandchild->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});
