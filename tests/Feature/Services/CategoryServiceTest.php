<?php

use App\Enums\DeletionStrategy;
use App\Enums\LifecycleStatus;
use App\Exceptions\Lifecycle\ChildrenExistException;
use App\Exceptions\Lifecycle\CircularReferenceException;
use App\Exceptions\Lifecycle\ParentNotActiveException;
use App\Models\Category;
use App\Models\CategoryProduct;
use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;
use App\Services\Category\CategoryService;
use App\Services\Lifecycle\LifecycleIntegrityService;
use Illuminate\Validation\ValidationException;

function categoryActor(): User
{
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    return $actor;
}

it('generates a slug from the name when none is given', function () {
    $category = app(CategoryService::class)->createCategory(['name' => 'Fresh Produce']);

    expect($category->slug)->toBe('fresh-produce');
});

it('blocks creating a category whose parent_id is itself', function () {
    $category = Category::factory()->create();

    expect(fn () => app(CategoryService::class)->updateCategory($category, ['parent_id' => $category->id]))
        ->toThrow(CircularReferenceException::class);
});

it('blocks re-parenting a category under one of its own descendants', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();

    expect(fn () => app(CategoryService::class)->updateCategory($root, ['parent_id' => $child->id]))
        ->toThrow(CircularReferenceException::class);
});

it('cascades deactivation to children and reactivation brings them back', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();
    $grandchild = Category::factory()->childOf($child)->create();

    app(CategoryService::class)->deactivateCategory($root);

    expect($child->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive)
        ->and($grandchild->refresh()->lifecycle_status)->toBe(LifecycleStatus::Inactive);

    app(CategoryService::class)->activateCategory($root->refresh());

    expect($child->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active)
        ->and($grandchild->refresh()->lifecycle_status)->toBe(LifecycleStatus::Active);
});

it('blocks activating a child directly while its parent is inactive', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();

    app(CategoryService::class)->deactivateCategory($root);
    $child->refresh();

    expect(fn () => app(CategoryService::class)->activateCategory($child))
        ->toThrow(ParentNotActiveException::class);
});

it('reports a category as not effectively active once its parent is inactive, even with its own status unchanged', function () {
    $root = Category::factory()->create();
    app(LifecycleIntegrityService::class)->deactivate($root);

    // A brand new category defaults to lifecycle_status = Active and
    // plain create() never fires the Activating event guardActivating()
    // listens for (that only fires from HasActiveStatus::activate()) -
    // so creating a child directly under an already-inactive parent is
    // the one way its own status can end up out of step with its
    // ancestor chain, which is exactly what isEffectivelyActive() exists
    // to catch that isLifecycleActive() alone would miss.
    $child = Category::factory()->childOf($root->refresh())->create();

    expect($child->isLifecycleActive())->toBeTrue()
        ->and(app(LifecycleIntegrityService::class)->isEffectivelyActive($child))->toBeFalse();
});

it('blocks deleting a category that still has products assigned', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create();
    $category->products()->attach($product);

    try {
        app(CategoryService::class)->deleteCategory($category);
        test()->fail('Expected CategoryService::deleteCategory() to throw.');
    } catch (ValidationException $e) {
        expect($e->errors()['category'][0])->toContain('1 product(s)');
    }

    expect($category->fresh()->trashed())->toBeFalse();
});

it('blocks deleting a category that still has children under the default deletion strategy', function () {
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create();

    expect(fn () => app(CategoryService::class)->deleteCategory($root))
        ->toThrow(ChildrenExistException::class);
});

it('deletes a childless category with no products', function () {
    $category = Category::factory()->create();

    app(CategoryService::class)->deleteCategory($category);

    expect($category->fresh()->trashed())->toBeTrue();
});

it('allows an explicit deletion strategy to override the default block', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();

    app(CategoryService::class)->deleteCategory($root, DeletionStrategy::DeleteSubtree);

    expect($child->refresh()->trashed())->toBeTrue();
});

it('blocks restoring a category while its parent is still trashed', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();

    app(CategoryService::class)->deleteCategory($root, DeletionStrategy::DeleteSubtree);

    expect(fn () => app(CategoryService::class)->restoreCategory($child->refresh()))
        ->toThrow(ValidationException::class);
});

it('force-deleting a category detaches its product pivot rows first', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create();
    $category->products()->attach($product);
    $category->delete();

    app(CategoryService::class)->forceDeleteCategory($category->fresh());

    expect(Category::withTrashed()->find($category->id))->toBeNull()
        ->and(CategoryProduct::withTrashed()->where('category_id', $category->id)->count())->toBe(0)
        ->and(Product::find($product->id))->not->toBeNull();
});

it('blocks force-deleting a category that still has children, even trashed ones', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();
    $child->delete();
    $root->delete();

    expect(fn () => app(CategoryService::class)->forceDeleteCategory($root->fresh()))
        ->toThrow(ValidationException::class);
});

it('selectOptions excludes the category itself and its descendants', function () {
    $root = Category::factory()->create(['name' => 'Root']);
    $child = Category::factory()->childOf($root)->create(['name' => 'Child']);
    $unrelated = Category::factory()->create(['name' => 'Unrelated']);

    $options = app(CategoryService::class)->selectOptions($root)->pluck('id');

    expect($options)->not->toContain($root->id)
        ->not->toContain($child->id)
        ->toContain($unrelated->id);
});

it('blocks nesting a category deeper than the max depth', function () {
    $root = Category::factory()->create();
    $level2 = Category::factory()->childOf($root)->create();
    $level3 = Category::factory()->childOf($level2)->create();

    expect(fn () => app(CategoryService::class)->createCategory(['name' => 'Too Deep', 'parent_id' => $level3->id]))
        ->toThrow(ValidationException::class);
});

it('bulk delete skips a row blocked by its own guard and reports a partial count, without aborting the rest', function () {
    $this->actingAs(categoryActor());

    $allowed = Category::factory()->create();
    $blocked = Category::factory()->create();
    $blocked->products()->attach(Product::factory()->create());

    $result = app(CategoryService::class)->bulkDelete([$allowed->id, $blocked->id]);

    expect($result)->toBe(['affected' => 1, 'skipped' => 1])
        ->and($allowed->fresh()->trashed())->toBeTrue()
        ->and($blocked->fresh()->trashed())->toBeFalse();
});

it('bulk actions skip every row for an actor with no admin.categories.edit permission at all', function () {
    $this->actingAs(User::factory()->create());

    $categories = Category::factory()->count(2)->create();

    $result = app(CategoryService::class)->bulkDeactivate($categories->pluck('id')->all());

    expect($result)->toBe(['affected' => 0, 'skipped' => 2]);
});
