<?php

namespace App\Jobs\Lifecycle;

use App\Contracts\LifecycleAware;
use App\Contracts\LifecycleStatusAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The queued half of a bulk cascade (see
 * LifecycleIntegrityService::cascadeChildrenOrQueue()): dispatched
 * instead of cascading inline once a relationship's affected-row count
 * crosses config('lifecycle.bulk_threshold'), so a 10,000+ row cascade
 * doesn't block the triggering request. Re-resolves the parent and
 * relation itself and chunks through it exactly like the synchronous
 * path does - the only difference is which process does the work.
 *
 * Each affected child still fires its own normal
 * Deactivated/deleting/etc. events, so recursion to grandchildren (and,
 * if a grandchild relation is itself over threshold, another queued
 * job) happens the same way it would inline.
 */
class CascadeLifecycleActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A transient failure (deadlock, brief DB unavailability) deserves a
     * retry before this is treated as a real, reportable failure - see
     * failed(), which only fires once every attempt is exhausted.
     */
    public int $tries = 3;

    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
        public readonly string $relationName,
        public readonly string $action,
        public readonly bool $force = false,
    ) {}

    public function handle(): void
    {
        $model = $this->modelClass::query()->find($this->modelId);

        if (! $model instanceof Model || ! $model instanceof LifecycleAware) {
            return;
        }

        $relation = $model->{$this->relationName}();

        if (! $relation instanceof Relation) {
            return;
        }

        $query = $this->force ? $relation->withTrashed() : $relation;

        $query->chunkById((int) config('lifecycle.chunk_size', 200), function ($children): void {
            foreach ($children as $child) {
                $this->applyToChild($child);
            }
        });
    }

    private function applyToChild(Model $child): void
    {
        if ($this->action === 'activate') {
            if ($child instanceof LifecycleStatusAware && ! $child->isLifecycleActive()) {
                $child->activate();
            }

            return;
        }

        if ($this->action === 'deactivate') {
            if ($child instanceof LifecycleStatusAware && $child->isLifecycleActive()) {
                $child->deactivate();
            }

            return;
        }

        if ($this->action === 'delete' && $child instanceof LifecycleAware) {
            if ($child->trashed() && ! $this->force) {
                return;
            }

            $this->force ? $child->forceDelete() : $child->delete();
        }
    }

    /**
     * Called once every retry (see $tries) is exhausted - the only signal
     * this module has that a queued cascade did NOT complete (Core
     * Business Rule 8: a triggering action that dispatched this job has
     * already committed and logged "queued N <Type> for background
     * processing" - see LifecycleIntegrityService::logCascadeAction() -
     * with no way to know at that point whether the job will actually
     * succeed). Logged rather than silently left for an operator to
     * discover via the framework's generic failed_jobs table; also
     * written to the activity log (gated by the same
     * lifecycle.activity_log.enabled config as every other cascade
     * entry) so it surfaces on the affected model's own audit trail, not
     * just the application log.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Queued lifecycle cascade failed.', [
            'model' => $this->modelClass,
            'model_id' => $this->modelId,
            'relation' => $this->relationName,
            'action' => $this->action,
            'force' => $this->force,
            'exception' => $exception,
        ]);

        if (! config('lifecycle.activity_log.enabled', true)) {
            return;
        }

        $model = $this->modelClass::query()->withTrashed()->find($this->modelId);

        if (! $model instanceof Model) {
            return;
        }

        activity()
            ->performedOn($model)
            ->withProperties(['relation' => $this->relationName, 'action' => $this->action])
            ->log(sprintf(
                'Queued %s cascade on %s failed for relation "%s" - it did not complete.',
                $this->action,
                class_basename($model),
                $this->relationName
            ));
    }
}
