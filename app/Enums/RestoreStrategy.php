<?php

namespace App\Enums;

enum RestoreStrategy: string
{
    case Cascade = 'cascade';
    case PendingActivation = 'pending_activation';
}
