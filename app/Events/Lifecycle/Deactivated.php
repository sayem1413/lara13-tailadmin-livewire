<?php

namespace App\Events\Lifecycle;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired by HasActiveStatus::deactivate() after the model is saved.
 * HasLifecycleIntegrity listens for this to cascade-deactivate related
 * records per the model's lifecycleRules().
 */
class Deactivated
{
    public function __construct(public readonly Model $model) {}
}
