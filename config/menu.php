<?php

/*
|--------------------------------------------------------------------------
| Sidebar Menu
|--------------------------------------------------------------------------
|
| Defines the admin sidebar as a list of groups, each with a label and an
| ordered list of items. An item may specify a "route" (a named route) or
| a "children" array of sub-items instead of a route. A "permission" may
| be a single permission/role name or an array of them - the item is shown
| if the user has at least one. Leave "permission" null to show an item to
| every authenticated user. Items whose route isn't registered (e.g. a
| module you haven't built yet) are hidden automatically by MenuService.
|
*/

return [

    [
        'group' => 'MAIN',
        'items' => [
            [
                'label' => 'Dashboard',
                'icon' => 'grid',
                'route' => 'admin.dashboard.index',
                'permission' => null,
                'order' => 1,
            ],
        ],
    ],

    [
        'group' => 'USER MANAGEMENT',
        'items' => [
            [
                'label' => 'Users',
                'icon' => 'users',
                'route' => 'admin.users.index',
                'permission' => 'admin.users.index',
                'order' => 1,
            ],
            [
                'label' => 'Roles & Permissions',
                'icon' => 'shield',
                'route' => 'admin.roles.index',
                'permission' => 'admin.roles.index',
                'order' => 2,
            ],
            [
                'label' => 'Activity Log',
                'icon' => 'clock',
                'route' => 'admin.activity-log.index',
                'permission' => 'admin.activity-log.index',
                'order' => 3,
            ],
        ],
    ],

    [
        'group' => 'SETTINGS',
        'items' => [
            [
                'label' => 'Settings',
                'icon' => 'settings',
                'route' => 'admin.settings.edit',
                'permission' => 'admin.settings.edit',
                'order' => 1,
            ],
        ],
    ],

];
