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
}
