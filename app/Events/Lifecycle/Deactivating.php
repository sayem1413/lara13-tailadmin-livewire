<?php

namespace App\Events\Lifecycle;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired by HasActiveStatus::deactivate() before the model is saved. A
 * listener that throws here aborts the deactivation - the exception
 * propagates straight out of deactivate().
 */
class Deactivating
{
    public function __construct(public readonly Model $model) {}
}
