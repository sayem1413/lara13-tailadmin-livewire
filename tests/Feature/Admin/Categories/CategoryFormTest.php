<?php

use App\Livewire\Admin\Categories\CategoryForm;
use App\Models\Category;
use App\Models\Permission\Permission;
use App\Models\User;
use Livewire\Livewire;

it('creates a category and auto-fills the slug from the name', function () {
    Permission::findOrCreate('admin.categories.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.create');

    Livewire::actingAs($actor)
        ->test(CategoryForm::class)
        ->set('name', 'Fresh Produce')
        ->call('save')
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::where('name', 'Fresh Produce')->first()?->slug)->toBe('fresh-produce');
});

it('validates that name is required', function () {
    Permission::findOrCreate('admin.categories.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.create');

    Livewire::actingAs($actor)
        ->test(CategoryForm::class)
        ->call('save')
        ->assertHasErrors('name');
});

it('loads an existing category for editing', function () {
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    $category = Category::factory()->create(['name' => 'Beverages']);

    Livewire::actingAs($actor)
        ->test(CategoryForm::class, ['category' => $category])
        ->assertSet('name', 'Beverages');
});

it('updates an existing category', function () {
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    $category = Category::factory()->create(['name' => 'Old Name']);

    Livewire::actingAs($actor)
        ->test(CategoryForm::class, ['category' => $category])
        ->set('name', 'New Name')
        ->call('save')
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh()->name)->toBe('New Name');
});

it('surfaces a circular-reference error rather than a 500 when a category is set as its own parent', function () {
    Permission::findOrCreate('admin.categories.edit');
    $actor = User::factory()->create();
    $actor->givePermissionTo('admin.categories.edit');

    $category = Category::factory()->create();

    Livewire::actingAs($actor)
        ->test(CategoryForm::class, ['category' => $category])
        ->set('parent_id', $category->id)
        ->call('save')
        ->assertHasErrors('parent_id');
});

it('forbids creating a category without the admin.categories.create permission', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(CategoryForm::class)
        ->assertForbidden();
});
