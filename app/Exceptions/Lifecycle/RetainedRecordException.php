<?php

namespace App\Exceptions\Lifecycle;

use Illuminate\Database\Eloquent\Model;

/**
 * Thrown when a force-delete (direct or cascaded) would permanently
 * destroy a record reached via a relationship marked `retain` in
 * lifecycleRules() - the safeguard behind Core Business Rule 2/6 for
 * historical, medical, financial, inventory, or audit records that must
 * never be permanently deletable through this module, regardless of how
 * the delete was triggered. Soft delete is unaffected; this only blocks
 * forceDelete().
 */
class RetainedRecordException extends LifecycleGuardException
{
    public static function make(Model $model): self
    {
        return new self(sprintf(
            'Cannot permanently delete this %s - it is marked `retain` and must not be force-deleted, directly or as part of a cascade.',
            class_basename($model)
        ));
    }
}
