<?php

namespace App\Exceptions\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a self-referential parent_id change would make a node its
 * own ancestor. Checked on every parent_id write, not just on create.
 */
class CircularReferenceException extends RuntimeException
{
    public static function make(Model $node): self
    {
        return new self(sprintf(
            'Cannot re-parent this %s to one of its own descendants - that would create a circular reference.',
            class_basename($node)
        ));
    }
}
