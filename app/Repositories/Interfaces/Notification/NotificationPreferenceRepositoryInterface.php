<?php

namespace App\Repositories\Interfaces\Notification;

use App\Models\User;
use Illuminate\Support\Collection;

interface NotificationPreferenceRepositoryInterface
{
    /**
     * The user's stored preference overrides, keyed by "type.channel" - a
     * key built from two non-empty database columns joined by a literal
     * ".", so it's never an empty or "0" string.
     *
     * @return Collection<non-falsy-string, bool>
     */
    public function forUser(User $user): Collection;

    /**
     * Persist a user's preference overrides.
     *
     * @param  array<int, array{type: string, channel: string, enabled: bool}>  $preferences
     */
    public function setMany(User $user, array $preferences): void;
}
