<?php

/*
|--------------------------------------------------------------------------
| Notification Types Schema
|--------------------------------------------------------------------------
|
| Defines every notification "type" the app can dispatch, for the
| preferences page and for NotificationService::send(). Each type has a
| label/description shown on the preferences form, and a "channels" map
| of channel => default enabled state. A channel here must match a
| Laravel notification channel name ("mail", "database", ...) - the
| notification class actually sent for a type decides how to render each
| channel; this schema only decides whether that channel fires.
|
| Add a new type here, then give the Notification class that represents
| it a `public static string $preferenceType = '<key>';` and use the
| App\Notifications\Concerns\RespectsNotificationPreferences trait - no
| further wiring needed for it to respect these preferences.
|
*/

return [

    'account_security' => [
        'label' => 'Account & Security',
        'description' => 'Changes to your account, such as role or permission updates made by an administrator.',
        'channels' => [
            'database' => true,
            'mail' => true,
        ],
    ],

];
