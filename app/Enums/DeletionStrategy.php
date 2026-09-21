<?php

namespace App\Enums;

enum DeletionStrategy: string
{
    case PromoteChildren = 'promote_children';
    case DeleteSubtree = 'delete_subtree';
}
