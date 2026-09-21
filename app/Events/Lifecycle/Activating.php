<?php

namespace App\Events\Lifecycle;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired by HasActiveStatus::activate() before the model is saved. A
 * listener (e.g. HasLifecycleIntegrity's guards) that throws here aborts
 * the activation - the exception propagates straight out of activate().
 */
class Activating
{
    public function __construct(public readonly Model $model) {}
}
