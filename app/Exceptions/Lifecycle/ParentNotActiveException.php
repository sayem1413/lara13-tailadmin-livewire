<?php

namespace App\Exceptions\Lifecycle;

use Illuminate\Database\Eloquent\Model;

/**
 * Thrown when an action would leave a record active while its direct
 * parent (exclusive relationships) or any ancestor up the chain
 * (hierarchical relationships) is not - covers both the "block
 * activating a child of an inactive parent" and "restoring requires
 * every ancestor to be active" guards from the same failure mode.
 *
 * Caught at the controller/Livewire layer and surfaced as a SweetAlert2
 * error toast - never a generic 500.
 */
class ParentNotActiveException extends LifecycleGuardException
{
    public static function forActivation(Model $child, Model $ancestor): self
    {
        return new self(sprintf(
            'Cannot activate this %s because its %s is not active.',
            class_basename($child),
            class_basename($ancestor)
        ));
    }

    public static function forRestore(Model $child, Model $ancestor): self
    {
        return new self(sprintf(
            'Cannot restore this %s because its %s is not active.',
            class_basename($child),
            class_basename($ancestor)
        ));
    }
}
