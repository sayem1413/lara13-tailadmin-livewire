<?php

use App\Models\Category;
use App\Models\Permission\Permission;
use App\Models\Product;
use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
});

it('forbids a user without the admin.categories.index permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

it('forbids creating a category without the admin.categories.create permission', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.categories.store'), ['name' => 'Beverages'])
        ->assertForbidden();
});

it('creates a category with the admin.categories.create permission', function () {
    Permission::findOrCreate('admin.categories.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.create');

    $this->actingAs($actor)
        ->post(route('admin.categories.store'), ['name' => 'Beverages'])
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::where('name', 'Beverages')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    Permission::findOrCreate('admin.categories.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.create');

    $this->actingAs($actor)
        ->post(route('admin.categories.store'), [])
        ->assertSessionHasErrors('name');
});

it('updates a category with the admin.categories.edit permission', function () {
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    $category = Category::factory()->create(['name' => 'Old Name']);

    $this->actingAs($actor)
        ->put(route('admin.categories.update', $category), ['name' => 'New Name'])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh()->name)->toBe('New Name');
});

it('shows a category to an actor with the admin.categories.index permission', function () {
    Permission::findOrCreate('admin.categories.index');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.index');

    $this->actingAs($actor)
        ->get(route('admin.categories.show', Category::factory()->create()))
        ->assertOk();
});

it('deletes a childless category with no products', function () {
    Permission::findOrCreate('admin.categories.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.destroy');

    $category = Category::factory()->create();

    $this->actingAs($actor)
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    $this->assertSoftDeleted($category);
});

it('refuses to delete a category that still has products, surfacing a validation error', function () {
    Permission::findOrCreate('admin.categories.destroy');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.destroy');

    $category = Category::factory()->create();
    $category->products()->attach(Product::factory()->create());

    $this->actingAs($actor)
        ->delete(route('admin.categories.destroy', $category))
        ->assertSessionHasErrors('category');

    expect($category->fresh()->trashed())->toBeFalse();
});

it('forbids restoring a category without the admin.categories.restore permission', function () {
    $category = Category::factory()->trashed()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('admin.categories.restore', $category))
        ->assertForbidden();
});

it('restores a soft-deleted category with the admin.categories.restore permission', function () {
    Permission::findOrCreate('admin.categories.restore');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.restore');

    $category = Category::factory()->trashed()->create();

    $this->actingAs($actor)
        ->put(route('admin.categories.restore', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh()->trashed())->toBeFalse();
});

it('permanently deletes a childless category with the admin.categories.force-delete permission', function () {
    Permission::findOrCreate('admin.categories.force-delete');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.force-delete');

    $category = Category::factory()->trashed()->create();

    $this->actingAs($actor)
        ->delete(route('admin.categories.force-delete', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::withTrashed()->find($category->id))->toBeNull();
});
