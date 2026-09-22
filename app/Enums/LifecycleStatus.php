<?php

namespace App\Enums;

enum LifecycleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case PendingActivation = 'pending_activation';
    case Archived = 'archived';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
