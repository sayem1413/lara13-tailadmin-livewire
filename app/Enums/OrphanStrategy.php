<?php

namespace App\Enums;

enum OrphanStrategy: string
{
    case AutoArchive = 'auto_archive';
    case Unassigned = 'unassigned';
    case PreventRemoval = 'prevent_removal';
}
