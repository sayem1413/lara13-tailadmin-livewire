<?php

namespace App\Exceptions\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown by the 'prevent_removal' orphan strategy when detaching a
 * shared (many-to-many) child from its last remaining parent.
 */
class OrphanRemovalBlockedException extends RuntimeException
{
    public static function make(Model $child, string $relation): self
    {
        return new self(sprintf(
            'Cannot remove this %s\'s last %s - reassign it to another parent or delete it explicitly first.',
            class_basename($child),
            $relation
        ));
    }
}
