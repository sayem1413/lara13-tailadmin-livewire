<?php

namespace App\Events\Lifecycle;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired by HasActiveStatus::activate() after the model is saved.
 * HasLifecycleIntegrity listens for this to cascade-restore/reactivate
 * related records per the model's lifecycleRules().
 */
class Activated
{
    public function __construct(public readonly Model $model) {}
}
