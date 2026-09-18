<?php

/*
|--------------------------------------------------------------------------
| Application Settings Schema
|--------------------------------------------------------------------------
|
| Defines the key-value settings editable from the Settings page, grouped
| into cards. Each field has a "type" (text, textarea, boolean, or select
| - select also needs an "options" map of value => label) and a "default"
| used until a value is saved to the settings table. Add new groups/fields
| here to extend the Settings page - no view or component changes needed.
|
*/

return [

    'general' => [
        'label' => 'General',
        'fields' => [
            'app_name' => [
                'label' => 'Application Name',
                'type' => 'text',
                'default' => config('app.name'),
            ],
            'support_email' => [
                'label' => 'Support Email',
                'type' => 'text',
                'default' => null,
            ],
            'maintenance_mode' => [
                'label' => 'Maintenance Mode',
                'type' => 'boolean',
                'default' => false,
            ],
        ],
    ],

];
