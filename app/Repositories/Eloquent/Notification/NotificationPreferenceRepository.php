<?php

namespace App\Repositories\Eloquent\Notification;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Repositories\Interfaces\Notification\NotificationPreferenceRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    public function __construct(
        protected NotificationPreference $model
    ) {}

    /**
     * @return Collection<non-falsy-string, bool>
     */
    public function forUser(User $user): Collection
    {
        return $this->model->query()
            ->where('user_id', $user->id)
            ->get(['type', 'channel', 'enabled'])
            ->mapWithKeys(fn (NotificationPreference $preference) => [
                "{$preference->type}.{$preference->channel}" => $preference->enabled,
            ]);
    }

    /**
     * @param  array<int, array{type: string, channel: string, enabled: bool}>  $preferences
     */
    public function setMany(User $user, array $preferences): void
    {
        DB::transaction(function () use ($user, $preferences) {
            foreach ($preferences as $preference) {
                $this->model->query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => $preference['type'],
                        'channel' => $preference['channel'],
                    ],
                    ['enabled' => $preference['enabled']],
                );
            }
        });
    }
}
