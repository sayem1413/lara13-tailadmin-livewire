<?php

/*
|--------------------------------------------------------------------------
| Application Settings Schema
|--------------------------------------------------------------------------
|
| Defines the key-value settings editable from the Settings page, grouped
| into cards. Each field has a "type" (text, textarea, boolean, or select
| - select also needs an "options" map of value => label), a "default"
| used until a value is saved to the settings table, and an optional
| "icon" (see x-ui.icon) shown inside a text field. Add new groups/fields
| here to extend the Settings page - no view or component changes needed.
|
| A "text" field also accepts an optional "input_type" (e.g. "email") to
| set the rendered <input>'s HTML type, and an optional "rules" array to
| override the type-based default validation in SettingService with a
| semantic one (email, url, numeric, ...).
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
                'input_type' => 'email',
                'icon' => 'mail',
                'default' => null,
                'rules' => ['nullable', 'email', 'max:255'],
            ],
            'maintenance_mode' => [
                'label' => 'Maintenance Mode',
                'type' => 'boolean',
                'default' => false,
            ],
        ],
    ],

];
