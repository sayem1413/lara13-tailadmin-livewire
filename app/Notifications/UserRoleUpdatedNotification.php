<?php

namespace App\Notifications;

use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Contracts\PreferenceAwareNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRoleUpdatedNotification extends Notification implements PreferenceAwareNotification
{
    use Queueable, RespectsNotificationPreferences;

    public static string $preferenceType = 'account_security';

    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        protected array $roles
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account roles were updated')
            ->line('An administrator updated the roles assigned to your account.')
            ->line('Your current roles: '.(empty($this->roles) ? 'none' : implode(', ', $this->roles)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'Your account roles were updated to: '.(empty($this->roles) ? 'none' : implode(', ', $this->roles)),
        ];
    }
}
