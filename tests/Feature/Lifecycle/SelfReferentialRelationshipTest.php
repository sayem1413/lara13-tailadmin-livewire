<?php

use App\Enums\DeletionStrategy;
use App\Enums\LifecycleStatus;
use App\Exceptions\Lifecycle\ChildrenExistException;
use App\Exceptions\Lifecycle\CircularReferenceException;
use App\Exceptions\Lifecycle\RetainedRecordException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoFolder;

beforeEach(function () {
    LifecycleDemoFolder::$deletionStrategy = 'delete_subtree';
    LifecycleDemoFolder::$childrenCascade = [];
    LifecycleDemoFolder::$childrenRetained = false;
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

it('blocks deleting a node under block_if_children_exist while it still has a child', function () {
    LifecycleDemoFolder::$deletionStrategy = 'block_if_children_exist';

    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);

    expect(fn () => lifecycleService()->deleteNode($root))
        ->toThrow(ChildrenExistException::class);

    expect($root->refresh()->trashed())->toBeFalse();
});

it('allows deleting a childless node under block_if_children_exist', function () {
    LifecycleDemoFolder::$deletionStrategy = 'block_if_children_exist';

    $leaf = LifecycleDemoFolder::create(['name' => 'Leaf']);

    lifecycleService()->deleteNode($leaf);

    expect($leaf->refresh()->trashed())->toBeTrue();
});

it('does not cascade activation down a self-referential subtree by default', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child', 'lifecycle_status' => 'inactive']);

    lifecycleService()->deactivate($root);
    lifecycleService()->activate($root);

    expect($child->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);
});

it('cascades activation down a self-referential subtree when explicitly opted in', function () {
    LifecycleDemoFolder::$childrenCascade = ['activate'];

    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);
    $grandchild = LifecycleDemoFolder::create(['parent_id' => $child->id, 'name' => 'Grandchild']);

    lifecycleService()->deactivate($root);
    lifecycleService()->activate($root);

    expect($child->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($grandchild->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('blocks a force-delete cascade from reaching a self-referential subtree marked retain', function () {
    LifecycleDemoFolder::$childrenRetained = true;

    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);

    expect(fn () => lifecycleService()->forceDelete($root))
        ->toThrow(RetainedRecordException::class);

    expect(LifecycleDemoFolder::withTrashed()->find($root->id))->not->toBeNull()
        ->and(LifecycleDemoFolder::withTrashed()->find($child->id))->not->toBeNull();
});

it('re-reads the prospective new parent with a lock before allowing a re-parent via reparent()', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $other = LifecycleDemoFolder::create(['name' => 'Other']);

    $selects = 0;
    DB::listen(function ($query) use (&$selects): void {
        if (str_contains($query->sql, 'lifecycle_demo_folders') && str_starts_with(trim($query->sql), 'select')) {
            $selects++;
        }
    });

    lifecycleService()->reparent($other, $root);

    // One locked re-read of $other's own row (lockRow()) plus one locked
    // read of $root as the prospective new parent - same SQLite caveat
    // as every other lockForUpdate() test in this module: this proves
    // the locked read happens, not that a real lock is held (see
    // ConcurrencyAndAuditTest).
    expect($selects)->toBeGreaterThanOrEqual(2)
        ->and($other->refresh()->parent_id)->toBe($root->id);
});

it('reparent() still runs the same circular-reference guard as a direct update() call', function () {
    $root = LifecycleDemoFolder::create(['name' => 'Root']);
    $child = LifecycleDemoFolder::create(['parent_id' => $root->id, 'name' => 'Child']);

    expect(fn () => lifecycleService()->reparent($root, $child))
        ->toThrow(CircularReferenceException::class);

    expect($root->refresh()->parent_id)->toBeNull();
});
