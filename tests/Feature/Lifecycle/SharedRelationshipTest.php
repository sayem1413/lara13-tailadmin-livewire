<?php

use App\Enums\LifecycleStatus;
use App\Exceptions\Lifecycle\OrphanRemovalBlockedException;
use App\Exceptions\Lifecycle\ParentNotActiveException;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoCategory;
use Tests\Fixtures\Lifecycle\Models\LifecycleDemoProduct;

beforeEach(function () {
    LifecycleDemoCategory::$orphanStrategy = 'prevent_removal';
});

it('links a child without affecting its status', function () {
    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);

    expect($category->products()->count())->toBe(1)
        ->and($product->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('blocks linking a child to an inactive parent', function () {
    $category = LifecycleDemoCategory::create(['name' => 'Electronics', 'lifecycle_status' => 'inactive']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    expect(fn () => lifecycleService()->link($category, 'products', $product))
        ->toThrow(ParentNotActiveException::class);

    expect($category->products()->count())->toBe(0);
});

it('unlinking a child from one of several parents does not touch the child', function () {
    $categoryA = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $categoryB = LifecycleDemoCategory::create(['name' => 'Deals']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($categoryA, 'products', $product);
    lifecycleService()->link($categoryB, 'products', $product);

    lifecycleService()->unlink($categoryA, 'products', $product);

    expect($product->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($product->categories()->count())->toBe(1)
        ->and($categoryA->products()->count())->toBe(0);
});

it('blocks removing a child\'s last link under the default prevent_removal strategy', function () {
    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);

    expect(fn () => lifecycleService()->unlink($category, 'products', $product))
        ->toThrow(OrphanRemovalBlockedException::class);

    expect($category->products()->count())->toBe(1);
});

it('auto-archives a child when its last link is removed under the auto_archive strategy', function () {
    LifecycleDemoCategory::$orphanStrategy = 'auto_archive';

    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);
    lifecycleService()->unlink($category, 'products', $product);

    expect($product->refresh()->lifecycle_status)->toBe(LifecycleStatus::Archived)
        ->and($category->products()->count())->toBe(0);
});

it('flags a child as unassigned when its last link is removed under the unassigned strategy', function () {
    LifecycleDemoCategory::$orphanStrategy = 'unassigned';

    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);
    lifecycleService()->unlink($category, 'products', $product);

    $product->refresh();

    expect($product->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($product->lifecycle_orphaned_at)->not->toBeNull();
});

it('clears a previously-unassigned child when it is linked again', function () {
    LifecycleDemoCategory::$orphanStrategy = 'unassigned';

    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);
    lifecycleService()->unlink($category, 'products', $product);
    expect($product->refresh()->lifecycle_orphaned_at)->not->toBeNull();

    lifecycleService()->link($category, 'products', $product);

    expect($product->refresh()->lifecycle_orphaned_at)->toBeNull();
});

it('only removes the pivot link when a parent is deactivated, leaving the child untouched', function () {
    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $productA = LifecycleDemoProduct::create(['name' => 'Laptop']);
    $productB = LifecycleDemoProduct::create(['name' => 'Mouse']);

    lifecycleService()->link($category, 'products', $productA);
    lifecycleService()->link($category, 'products', $productB);

    lifecycleService()->deactivate($category);

    expect($productA->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($productB->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($category->products()->count())->toBe(0);
});

it('falls back to unassigned instead of blocking when a parent cascade would orphan a child under prevent_removal', function () {
    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);

    lifecycleService()->deactivate($category);

    $product->refresh();

    expect($product->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($product->lifecycle_orphaned_at)->not->toBeNull();
});

it('severs pivot links when a parent is deleted', function () {
    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);
    lifecycleService()->delete($category);

    expect(LifecycleDemoProduct::find($product->id))->not->toBeNull()
        ->and($category->products()->count())->toBe(0);
});

it('hard-removes pivot rows when a parent is force deleted, to satisfy the FK restrict constraint', function () {
    $category = LifecycleDemoCategory::create(['name' => 'Electronics']);
    $product = LifecycleDemoProduct::create(['name' => 'Laptop']);

    lifecycleService()->link($category, 'products', $product);

    lifecycleService()->forceDelete($category);

    expect(LifecycleDemoProduct::find($product->id))->not->toBeNull();
});
