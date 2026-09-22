<?php

namespace App\Notifications\Contracts;

/**
 * Implemented by a Notification class dispatched through
 * NotificationService::send(), which resolves the recipient's enabled
 * channels from config/notification_types.php and calls withChannels()
 * before notify(). Use the
 * App\Notifications\Concerns\RespectsNotificationPreferences trait for the
 * implementation - it satisfies this contract on its own aside from the
 * `public static string $preferenceType` property the trait reads.
 */
interface PreferenceAwareNotification
{
    /**
     * @param  array<int, string>  $channels
     */
    public function withChannels(array $channels): static;

    public static function preferenceType(): string;
}
