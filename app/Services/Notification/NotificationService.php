<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Notifications\Contracts\PreferenceAwareNotification;
use App\Repositories\Interfaces\Notification\NotificationPreferenceRepositoryInterface;
use App\Repositories\Interfaces\Notification\NotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function __construct(
        protected NotificationRepositoryInterface $notificationRepository,
        protected NotificationPreferenceRepositoryInterface $preferenceRepository
    ) {}

    /**
     * Send a notification to $user through whichever of its declared
     * channels the user hasn't opted out of, resolved from their stored
     * preferences (falling back to config/notification_types.php's
     * defaults). Sends nothing if every channel is disabled. This is the
     * entry point for dispatching a preference-aware notification from
     * anywhere in the app: app(NotificationService::class)->send($user,
     * new SomeNotification(...));
     */
    public function send(User $user, Notification&PreferenceAwareNotification $notification): void
    {
        $channels = $this->channelsFor($user, $notification::preferenceType());

        if ($channels === []) {
            return;
        }

        // Callers (e.g. UserService::updateUser()) may dispatch this from
        // inside their own DB::transaction() around the action the
        // notification is about. A failed channel (mail server down, etc.)
        // must not roll back that unrelated business change, so the
        // failure is swallowed here rather than left to propagate - but
        // it's logged since it would otherwise fail completely silently.
        try {
            $user->notify($notification->withChannels($channels));
        } catch (Throwable $e) {
            Log::error('Failed to dispatch notification.', [
                'notification' => $notification::class,
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * The channels enabled for $user on notification $type, merging their
     * stored overrides on top of config/notification_types.php's defaults.
     *
     * @return array<int, string>
     */
    public function channelsFor(User $user, string $type): array
    {
        $overrides = $this->preferenceRepository->forUser($user);
        $defaults = $this->types()[$type]['channels'] ?? [];

        return collect($defaults)
            ->map(fn (bool $default, string $channel) => $overrides->get("{$type}.{$channel}", $default))
            ->filter()
            ->keys()
            ->all();
    }

    /**
     * Every configured type/channel pair for the preferences form, with
     * $user's current effective (override-or-default) enabled state.
     *
     * @return array<string, array{label: string, description: string, channels: array<string, bool>}>
     */
    public function preferencesFor(User $user): array
    {
        $overrides = $this->preferenceRepository->forUser($user);

        return collect($this->types())
            ->map(fn (array $type, string $key) => [
                'label' => $type['label'],
                'description' => $type['description'],
                'channels' => collect($type['channels'])
                    ->mapWithKeys(fn (bool $default, string $channel) => [
                        $channel => $overrides->get("{$key}.{$channel}", $default),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * The notification types schema from config/notification_types.php.
     *
     * @return array<string, array{label: string, description: string, channels: array<string, bool>}>
     */
    protected function types(): array
    {
        /** @var array<string, array{label: string, description: string, channels: array<string, bool>}> $types */
        $types = config('notification_types', []);

        return $types;
    }

    /**
     * Persist a user's submitted preference overrides.
     *
     * @param  array<string, array<string, bool>>  $values  type => [channel => enabled]
     */
    public function updatePreferences(User $user, array $values): void
    {
        $preferences = [];

        foreach ($values as $type => $channels) {
            foreach ($channels as $channel => $enabled) {
                $preferences[] = ['type' => $type, 'channel' => $channel, 'enabled' => (bool) $enabled];
            }
        }

        $this->preferenceRepository->setMany($user, $preferences);
    }

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->notificationRepository->paginateForUser($user, $perPage);
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function latestForUser(User $user, int $limit): Collection
    {
        return $this->notificationRepository->latestForUser($user, $limit);
    }

    public function unreadCountForUser(User $user): int
    {
        return $this->notificationRepository->unreadCountForUser($user);
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $this->notificationRepository->markAsRead($user, $notificationId);
    }

    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user);
    }
}
