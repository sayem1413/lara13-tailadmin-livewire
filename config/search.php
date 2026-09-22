<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Global Search Modules
|--------------------------------------------------------------------------
|
| Each entry describes one searchable module for the header's global search.
| "columns" are matched with a LIKE query, "title"/"description" are the
| attribute (or dot-notation accessor) shown for each result, and "route"
| (with "route_param") builds the link to the matched record. A module is
| skipped automatically if its route isn't registered yet or the current
| user lacks its "permission". Add an entry here for each new module you
| want to appear in search - no changes to the search component itself.
|
*/

return [

    'modules' => [
        [
            'label' => 'Users',
            'model' => User::class,
            'columns' => ['name', 'email'],
            'title' => 'name',
            'description' => 'email',
            'route' => 'admin.users.show',
            'route_param' => 'user',
            'permission' => 'admin.users.index',
        ],
        [
            'label' => 'Products',
            'model' => Product::class,
            'columns' => ['name', 'sku', 'barcode'],
            'title' => 'name',
            'description' => 'sku',
            'route' => 'admin.products.show',
            'route_param' => 'product',
            'permission' => 'admin.products.index',
        ],
        [
            'label' => 'Categories',
            'model' => Category::class,
            'columns' => ['name', 'slug'],
            'title' => 'name',
            'description' => 'slug',
            'route' => 'admin.categories.show',
            'route_param' => 'category',
            'permission' => 'admin.categories.index',
        ],
    ],

];
