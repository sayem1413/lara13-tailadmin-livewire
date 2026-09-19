<?php

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
            'route' => 'admin.users.edit',
            'route_param' => 'user',
            'permission' => 'admin.users.index',
        ],
    ],

];
