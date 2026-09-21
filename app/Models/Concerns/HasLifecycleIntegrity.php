<?php

namespace App\Models\Concerns;

use App\Contracts\LifecycleAware;
use App\Events\Lifecycle\Activating;
use App\Events\Lifecycle\Deactivated;
use App\Services\Lifecycle\LifecycleIntegrityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Wires a model's lifecycle events (the custom Activating/Deactivated
 * from HasActiveStatus, plus Eloquent's native deleting/deleted/
 * restoring/restored) to LifecycleIntegrityService, which reads the
 * model's own lifecycleRules() (see App\Contracts\LifecycleAware) and
 * performs every guard/cascade. No business logic lives here - see
 * docs/lifecycle-integrity.md.
 *
 * Requires the model to also implement LifecycleAware (which itself
 * requires HasActiveStatus's activate()/deactivate()) and use SoftDeletes.
 */
trait HasLifecycleIntegrity
{
    protected static function bootHasLifecycleIntegrity(): void
    {
        $modelClass = static::class;

        Event::listen(Activating::class, function (Activating $event) use ($modelClass): void {
            if ($event->model instanceof $modelClass) {
                app(LifecycleIntegrityService::class)->guardActivating($event->model);
            }
        });

        Event::listen(Deactivated::class, function (Deactivated $event) use ($modelClass): void {
            if ($event->model instanceof $modelClass) {
                app(LifecycleIntegrityService::class)->cascadeDeactivated($event->model);
            }
        });

        static::restoring(function (Model $model): void {
            if ($model instanceof LifecycleAware) {
                app(LifecycleIntegrityService::class)->guardRestoring($model);
            }
        });

        static::deleting(function (Model $model): void {
            if ($model instanceof LifecycleAware) {
                app(LifecycleIntegrityService::class)->cascadeDeleted($model);
            }
        });

        static::restored(function (Model $model): void {
            if ($model instanceof LifecycleAware) {
                app(LifecycleIntegrityService::class)->cascadeRestored($model);
            }
        });

        // The following four are no-ops for a model with no
        // self_referential rule (LifecycleIntegrityService checks and
        // returns early) - registered unconditionally since a trait
        // can't know in advance which rule types a given model declares.
        static::updating(function (Model $model): void {
            if ($model instanceof LifecycleAware) {
                app(LifecycleIntegrityService::class)->guardCircularReference($model);
            }
        });

        static::created(function (Model $model): void {
            if ($model instanceof LifecycleAware) {
                app(LifecycleIntegrityService::class)->syncClosureTable($model);
            }
        });

        static::updated(function (Model $model): void {
            if ($model instanceof LifecycleAware && $model->wasChanged('parent_id')) {
                app(LifecycleIntegrityService::class)->syncClosureTable($model);
            }
        });

        static::forceDeleted(function (Model $model): void {
            if ($model instanceof LifecycleAware) {
                app(LifecycleIntegrityService::class)->forgetClosureRows($model);
            }
        });
    }
}
