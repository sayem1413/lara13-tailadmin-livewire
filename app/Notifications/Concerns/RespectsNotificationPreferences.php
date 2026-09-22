<?php

namespace App\Notifications\Concerns;

/**
 * For a Notification class that should be dispatched through
 * NotificationService::send(), which resolves the recipient's enabled
 * channels from config/notification_types.php and their stored overrides,
 * then calls withChannels() before notify() so via() only returns
 * channels the user actually opted into.
 *
 * A class using this trait must declare `public static string
 * $preferenceType` matching a key in config/notification_types.php.
 */
trait RespectsNotificationPreferences
{
    /** @var array<int, string> */
    protected array $enabledChannels = [];

    public function withChannels(array $channels): static
    {
        $this->enabledChannels = $channels;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->enabledChannels;
    }

    public static function preferenceType(): string
    {
        return static::$preferenceType;
    }
}
