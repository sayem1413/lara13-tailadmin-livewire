<?php

namespace App\Exceptions\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown by deleteNode() under the `block_if_children_exist` deletion
 * strategy when the node being deleted still has at least one child -
 * the caller must first move or delete those children (or choose
 * `promote_children`/`delete_subtree` instead) before this node can be
 * removed.
 */
class ChildrenExistException extends RuntimeException
{
    public static function make(Model $node): self
    {
        return new self(sprintf(
            'Cannot delete this %s - it still has children. Move or delete them first, or use a different deletion strategy.',
            class_basename($node)
        ));
    }
}
