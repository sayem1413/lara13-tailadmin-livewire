<?php

namespace App\Enums;

enum LifecycleRelationType: string
{
    case Exclusive = 'exclusive';
    case Shared = 'shared';
    case Hierarchical = 'hierarchical';
    case SelfReferential = 'self_referential';
}
