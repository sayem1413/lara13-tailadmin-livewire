<?php

namespace App\Services\Lifecycle;

use App\Contracts\LifecycleAware;
use App\Contracts\LifecycleStatusAware;
use App\Enums\DeletionStrategy;
use App\Enums\LifecycleRelationType;
use App\Enums\LifecycleStatus;
use App\Enums\OrphanStrategy;
use App\Enums\RestoreStrategy;
use App\Exceptions\Lifecycle\CircularReferenceException;
use App\Exceptions\Lifecycle\OrphanRemovalBlockedException;
use App\Exceptions\Lifecycle\ParentNotActiveException;
use App\Jobs\Lifecycle\CascadeLifecycleActionJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * All guard/cascade/transaction logic for the Entity Lifecycle module.
 * HasLifecycleIntegrity only wires events to the methods below - see
 * docs/lifecycle-integrity.md for the full behavior per relationship
 * type. Bound as a singleton (see AppServiceProvider) because the
 * `cascading` flag below must be shared across every guard/cascade call
 * within one triggering action's call stack.
 */
class LifecycleIntegrityService
{
    /**
     * True while a cascade this service started is still unwinding.
     * Guards (guardActivating/guardRestoring) skip their check while
     * this is true: a cascade-triggered restore/activate of a child is
     * already authorized by the triggering parent action, and the
     * child's own ancestor-status may not be "settled" mid-cascade (see
     * cascadeRestored()) - only a direct, independent call needs guarding.
     */
    private bool $cascading = false;

    /**
     * Per-type counts of records affected by the cascade currently
     * unwinding, for the grouped activity log entry each public entry
     * point below writes once its transaction commits (see
     * logCascadeAction()). Reset at the start of each entry point, not
     * inside withinCascade(), since a top-level action with no cascade
     * at all (e.g. an exclusive relationship with no children yet) must
     * still log its own action with an empty breakdown.
     *
     * @var array<string, int>
     */
    private array $affectedCounts = [];

    public function __construct(private readonly ClosureTableManager $closures) {}

    // ---- Public entry points -------------------------------------------------

    public function activate(LifecycleAware&Model $model): void
    {
        if ($model->isLifecycleActive()) {
            return;
        }

        DB::transaction(function () use ($model): void {
            $this->lockRow($model);
            $model->activate();
        });
    }

    public function deactivate(LifecycleAware&Model $model): void
    {
        if (! $model->isLifecycleActive()) {
            return;
        }

        $this->affectedCounts = [];

        DB::transaction(function () use ($model): void {
            $this->lockRow($model);
            $model->deactivate();
        });

        $this->logCascadeAction($model, 'Deactivated');
    }

    public function delete(LifecycleAware&Model $model): void
    {
        if ($model->trashed()) {
            return;
        }

        $this->affectedCounts = [];

        DB::transaction(function () use ($model): void {
            $this->lockRow($model);
            $model->delete();
        });

        $this->logCascadeAction($model, 'Deleted');
    }

    public function forceDelete(LifecycleAware&Model $model): void
    {
        $this->affectedCounts = [];

        DB::transaction(function () use ($model): void {
            $this->lockRow($model);
            $model->forceDelete();
        });

        $this->logCascadeAction($model, 'Permanently deleted');
    }

    public function restore(LifecycleAware&Model $model): void
    {
        if (! $model->trashed()) {
            return;
        }

        $this->affectedCounts = [];

        DB::transaction(function () use ($model): void {
            $this->lockRow($model);
            $model->restore();
        });

        $this->logCascadeAction($model, 'Restored');
    }

    /**
     * Links a child into a shared (many-to-many) relationship. Blocks
     * linking to an inactive/deleted parent. Clears the child's
     * lifecycle_orphaned_at unconditionally (a harmless no-op if it
     * wasn't set) - being linked to a parent again means it's no longer
     * unassigned.
     */
    public function link(LifecycleAware&Model $parent, string $ruleKey, Model $child): void
    {
        DB::transaction(function () use ($parent, $ruleKey, $child): void {
            $this->lockRow($parent);

            if (! $parent->isLifecycleActive() || $parent->trashed()) {
                throw ParentNotActiveException::forActivation($child, $parent);
            }

            $relation = $this->sharedRelation($parent, $this->sharedRule($parent, $ruleKey));

            if (! $relation instanceof BelongsToMany) {
                return;
            }

            $relation->attach($child->getKey(), ['created_by' => Auth::id()]);

            $child->forceFill(['lifecycle_orphaned_at' => null])->save();
        });
    }

    /**
     * Severs a child from a shared (many-to-many) relationship. If this
     * is the child's last remaining link for this relationship, applies
     * its orphan_strategy - including, for 'prevent_removal', blocking
     * the removal entirely with OrphanRemovalBlockedException.
     */
    public function unlink(LifecycleAware&Model $parent, string $ruleKey, Model $child): void
    {
        $this->affectedCounts = [];

        DB::transaction(function () use ($parent, $ruleKey, $child): void {
            $this->lockRow($parent);
            $this->severSharedLink($parent, $this->sharedRule($parent, $ruleKey), $child, allowBlocking: true);
        });

        $this->logCascadeAction($parent, 'Unlinked a '.class_basename($child).' from');
    }

    /**
     * Deletes a self-referential node, per a strategy chosen at the call
     * site (not just globally configured - see config/lifecycle.php's
     * `defaults.self_referential.deletion_strategy` for the fallback):
     * DeleteSubtree cascades the delete to the node's entire subtree
     * (reusing the same recursive cascade every other relationship type
     * uses - see cascadeDeleted()). PromoteChildren instead re-parents
     * the node's direct children to its own parent first, so only this
     * one node is removed.
     */
    public function deleteNode(LifecycleAware&Model $model, ?DeletionStrategy $strategy = null): void
    {
        if ($model->trashed()) {
            return;
        }

        $strategy ??= $this->deletionStrategy($model);
        $this->affectedCounts = [];

        DB::transaction(function () use ($model, $strategy): void {
            $this->lockRow($model);

            if ($strategy === DeletionStrategy::PromoteChildren) {
                $this->promoteChildren($model);
            }

            $model->delete();
        });

        $this->logCascadeAction($model, 'Deleted');
    }

    /**
     * Blocks a self-referential parent_id change that would make a node
     * its own ancestor - checked on every update where parent_id is
     * dirty, not just at creation (a brand new node can never already
     * have descendants, so it can never form a cycle).
     */
    public function guardCircularReference(LifecycleAware&Model $model): void
    {
        $rule = $this->selfReferentialRule($model);

        if ($rule === null || ! $model->isDirty('parent_id')) {
            return;
        }

        $newParentId = $model->getAttribute('parent_id');

        if ($newParentId === null) {
            return;
        }

        if ((string) $newParentId === (string) $model->getKey()) {
            throw CircularReferenceException::make($model);
        }

        $newParent = $model->newQuery()->find($newParentId);

        if ($newParent instanceof Model && $this->closures->isDescendantOf($newParent, $model)) {
            throw CircularReferenceException::make($model);
        }
    }

    /**
     * Keeps the shared closure table in sync with a self-referential
     * node's parent_id - called after both creation and any update
     * where parent_id changed.
     */
    public function syncClosureTable(LifecycleAware&Model $model): void
    {
        $rule = $this->selfReferentialRule($model);

        if ($rule === null) {
            return;
        }

        $parentRelationName = (string) ($rule['parent_relation'] ?? 'parent');
        $model->unsetRelation($parentRelationName);
        $parent = $model->{$parentRelationName};

        $this->closures->reparent($model, $parent instanceof Model ? $parent : null);
    }

    /**
     * Removes a force-deleted self-referential node's own closure rows
     * (see ClosureTableManager::forget()) - a no-op for a model with no
     * self_referential rule.
     */
    public function forgetClosureRows(LifecycleAware&Model $model): void
    {
        if ($this->selfReferentialRule($model) === null) {
            return;
        }

        $this->closures->forget($model);
    }

    /**
     * Re-parents a self-referential node's direct children to its own
     * parent (see deleteNode()'s PromoteChildren strategy) - each
     * child's own `updating`/`updated` hooks run normally, so the
     * circular-reference guard and closure table stay correct.
     */
    private function promoteChildren(LifecycleAware&Model $model): void
    {
        $rule = $this->selfReferentialRule($model);

        if ($rule === null) {
            return;
        }

        $parentRelationName = (string) ($rule['parent_relation'] ?? 'parent');
        $grandparent = $model->{$parentRelationName};
        $newParentId = $grandparent instanceof Model ? $grandparent->getKey() : null;

        $this->eachChild($model, $this->childrenRule($rule), static function (Model $child) use ($newParentId): void {
            $child->setAttribute('parent_id', $newParentId);
            $child->save();
        });
    }

    // ---- Guards (wired to Activating / native restoring) ---------------------

    /**
     * Blocks activating a record whose parent, or any ancestor up the
     * chain, is not active.
     */
    public function guardActivating(LifecycleAware&Model $model): void
    {
        if ($this->cascading) {
            return;
        }

        foreach ($this->ancestors($model) as $ancestor) {
            if (! $ancestor->isLifecycleActive()) {
                throw ParentNotActiveException::forActivation($model, $ancestor);
            }
        }
    }

    /**
     * Blocks restoring a record while its parent, or any ancestor up the
     * chain, is not active - the same ancestor walk as guardActivating(),
     * applied to a restore instead.
     */
    public function guardRestoring(LifecycleAware&Model $model): void
    {
        if ($this->cascading) {
            return;
        }

        foreach ($this->ancestors($model) as $ancestor) {
            if (! $ancestor->isLifecycleActive()) {
                throw ParentNotActiveException::forRestore($model, $ancestor);
            }
        }
    }

    // ---- Cascades (wired to Deactivated / native deleted / native restored) --

    /**
     * Cascades deactivation down every exclusive/hierarchical child
     * relationship this model owns whose `cascade` list includes
     * 'deactivate'. Recurses naturally: each affected child's own
     * deactivate() fires the same Deactivated event for its children.
     */
    public function cascadeDeactivated(LifecycleAware&Model $model): void
    {
        $this->withinCascade(function () use ($model): void {
            $deactivateChild = function (Model $child): void {
                if ($child instanceof LifecycleStatusAware && $child->isLifecycleActive()) {
                    $child->deactivate();
                    $this->recordAffected($child);
                }
            };

            foreach ($this->downwardRules($model) as $rule) {
                if (in_array('deactivate', $this->cascadeTriggers($rule), true)) {
                    $this->cascadeChildrenOrQueue($model, $rule, 'deactivate', force: false, inlineCallback: $deactivateChild);
                }
            }

            foreach ($this->sharedRules($model) as $rule) {
                $this->cascadeSeverShared($model, $rule, hard: false);
            }

            foreach ($this->selfReferentialRules($model) as $rule) {
                $this->cascadeChildrenOrQueue($model, $this->childrenRule($rule), 'deactivate', force: false, inlineCallback: $deactivateChild);
            }
        });
    }

    /**
     * Cascades a soft delete or force delete (matching the parent's own)
     * down every exclusive/hierarchical child relationship this model
     * owns whose `cascade` list includes 'delete'. Recurses naturally.
     *
     * Wired to the native `deleting` event (before the parent's own row
     * is touched), not `deleted`: a force-deleted parent's row is
     * removed immediately as part of its own delete() call, and every FK
     * this module creates is `onDelete('restrict')` (see
     * docs/lifecycle-integrity.md) - children must already be force-
     * deleted by the time that row delete executes, or it violates the
     * constraint.
     */
    public function cascadeDeleted(LifecycleAware&Model $model): void
    {
        $force = $model->isForceDeleting();

        $this->withinCascade(function () use ($model, $force): void {
            $deleteChild = function (Model $child) use ($force): void {
                if (! $child instanceof LifecycleAware) {
                    return;
                }

                if ($child->trashed() && ! $force) {
                    return;
                }

                $force ? $child->forceDelete() : $child->delete();
                $this->recordAffected($child);
            };

            foreach ($this->downwardRules($model) as $rule) {
                if (in_array('delete', $this->cascadeTriggers($rule), true)) {
                    $this->cascadeChildrenOrQueue($model, $rule, 'delete', $force, $deleteChild);
                }
            }

            foreach ($this->sharedRules($model) as $rule) {
                $this->cascadeSeverShared($model, $rule, hard: $force);
            }

            foreach ($this->selfReferentialRules($model) as $rule) {
                $this->cascadeChildrenOrQueue($model, $this->childrenRule($rule), 'delete', $force, $deleteChild);
            }
        });
    }

    /**
     * Restores children per the relationship's restore_strategy:
     * 'cascade' restores and reactivates them immediately; the default,
     * safer 'pending_activation' restores the row but holds status at
     * pending_activation for an operator to explicitly reactivate.
     * Recurses naturally via each restored child's own native `restored`
     * event.
     */
    public function cascadeRestored(LifecycleAware&Model $model): void
    {
        $this->withinCascade(function () use ($model): void {
            foreach ($this->downwardRules($model) as $rule) {
                $strategy = $this->restoreStrategy($rule);

                $this->eachChild($model, $rule, function (Model $child) use ($strategy): void {
                    if (! $child instanceof LifecycleAware || ! $child->trashed()) {
                        return;
                    }

                    $child->restore();

                    if ($strategy === RestoreStrategy::Cascade) {
                        $child->activate();

                        return;
                    }

                    $child->forceFill(['lifecycle_status' => LifecycleStatus::PendingActivation])->save();
                }, withTrashed: true);
            }
        });
    }

    // ---- Private helpers -------------------------------------------------

    private function withinCascade(\Closure $callback): void
    {
        $previous = $this->cascading;
        $this->cascading = true;

        try {
            $callback();
        } finally {
            $this->cascading = $previous;
        }
    }

    /**
     * SELECT ... FOR UPDATE on $model's own row, discarding the result -
     * used purely for the lock it holds for the rest of the current
     * transaction, preventing a concurrent transaction from reading or
     * writing this row until this one commits (e.g. two admins
     * deactivating the same parent and reassigning a child at the same
     * time). A no-op outside a transaction.
     */
    private function lockRow(LifecycleAware&Model $model): void
    {
        $model->newQuery()->lockForUpdate()->find($model->getKey());
    }

    /**
     * @return \Generator<int, Model&LifecycleStatusAware>
     */
    private function ancestors(LifecycleAware&Model $model): \Generator
    {
        foreach ($this->upwardRules($model) as $rule) {
            $relationName = $rule['parent_relation'] ?? null;

            if (! is_string($relationName)) {
                continue;
            }

            $relation = $model->{$relationName}();

            if (! $relation instanceof Relation) {
                continue;
            }

            // Locked for the same reason as lockRow() above - this
            // guard's whole point is reading the parent's authoritative,
            // not-concurrently-changing status.
            $parent = $relation->lockForUpdate()->first();

            if (! $parent instanceof Model) {
                continue;
            }

            if ($parent instanceof LifecycleStatusAware) {
                yield $parent;
            }

            if ($parent instanceof LifecycleAware) {
                yield from $this->ancestors($parent);
            }
        }
    }

    private function recordAffected(Model $child): void
    {
        $key = class_basename($child);
        $this->affectedCounts[$key] = ($this->affectedCounts[$key] ?? 0) + 1;
    }

    /**
     * Writes ONE grouped activity log entry for a triggering action,
     * with a per-type breakdown of everything the cascade touched (e.g.
     * "Deactivated Organization (cascaded to 3 Department, 42
     * Employee)") rather than one log row per affected record - avoids
     * flooding the audit log on a large cascade. Affected ids aren't
     * stored (only counts) to keep the properties payload small; add
     * them here if a drill-down view needs them.
     */
    private function logCascadeAction(Model $model, string $verb): void
    {
        if (! config('lifecycle.activity_log.enabled', true)) {
            return;
        }

        $breakdown = collect($this->affectedCounts)
            ->map(fn (int $count, string $type): string => "{$count} {$type}")
            ->implode(', ');

        $description = $breakdown !== ''
            ? sprintf('%s %s (cascaded to %s)', $verb, class_basename($model), $breakdown)
            : sprintf('%s %s', $verb, class_basename($model));

        activity()
            ->performedOn($model)
            ->withProperties(['affected' => $this->affectedCounts])
            ->log($description);
    }

    /**
     * Cascades one relationship's children inline (chunked, as today)
     * if its current row count is under the bulk threshold, or dispatches
     * a CascadeLifecycleActionJob instead once it's at or over it - see
     * config('lifecycle.bulk_threshold'). Only used for the two cascade
     * actions bulk enough to matter in practice (deactivate/delete);
     * restore's per-child strategy branching isn't queued.
     *
     * @param  array<string, mixed>  $rule
     */
    private function cascadeChildrenOrQueue(LifecycleAware&Model $model, array $rule, string $action, bool $force, \Closure $inlineCallback): void
    {
        $relationName = $rule['relation'] ?? null;

        if (! is_string($relationName)) {
            return;
        }

        $relation = $model->{$relationName}();

        if (! $relation instanceof Relation) {
            return;
        }

        $query = $force ? $relation->withTrashed() : $relation;
        $count = $query->count();

        if ($count >= (int) config('lifecycle.bulk_threshold', 500)) {
            $this->affectedCounts["queued:{$relationName}"] = $count;

            CascadeLifecycleActionJob::dispatch($model::class, $model->getKey(), $relationName, $action, $force)
                ->onConnection(config('lifecycle.queue.connection'))
                ->onQueue(config('lifecycle.queue.queue'));

            return;
        }

        $this->eachChild($model, $rule, $inlineCallback, withTrashed: $force);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function downwardRules(LifecycleAware $model): array
    {
        return array_values(array_filter(
            $model->lifecycleRules(),
            fn (array $rule): bool => $this->isExclusiveLike($rule) && isset($rule['relation'])
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upwardRules(LifecycleAware $model): array
    {
        return array_values(array_filter(
            $model->lifecycleRules(),
            fn (array $rule): bool => $this->isExclusiveLike($rule) && isset($rule['parent_relation'])
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sharedRules(LifecycleAware $model): array
    {
        return array_values(array_filter(
            $model->lifecycleRules(),
            fn (array $rule): bool => $this->relationType($rule) === LifecycleRelationType::Shared && isset($rule['relation'])
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function selfReferentialRules(LifecycleAware $model): array
    {
        return array_values(array_filter(
            $model->lifecycleRules(),
            fn (array $rule): bool => $this->relationType($rule) === LifecycleRelationType::SelfReferential
        ));
    }

    /**
     * The first self_referential rule declared, if any - a model is not
     * expected to declare more than one.
     *
     * @return array<string, mixed>|null
     */
    private function selfReferentialRule(LifecycleAware $model): ?array
    {
        return $this->selfReferentialRules($model)[0] ?? null;
    }

    /**
     * A self_referential rule doesn't require a `relation` key the way
     * exclusive/shared rules do (its children relation defaults to
     * 'children'), but eachChild() needs one - this fills in the default.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function childrenRule(array $rule): array
    {
        $rule['relation'] ??= 'children';

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function isExclusiveLike(array $rule): bool
    {
        return in_array($this->relationType($rule), [LifecycleRelationType::Exclusive, LifecycleRelationType::Hierarchical], true);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function relationType(array $rule): ?LifecycleRelationType
    {
        return LifecycleRelationType::tryFrom((string) ($rule['type'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<int, string>
     */
    private function cascadeTriggers(array $rule): array
    {
        /** @var array<int, string> $triggers */
        $triggers = $rule['cascade'] ?? ['deactivate', 'delete'];

        return $triggers;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function restoreStrategy(array $rule): RestoreStrategy
    {
        $value = $rule['restore_strategy'] ?? config('lifecycle.defaults.exclusive.restore_strategy');

        return RestoreStrategy::from((string) $value);
    }

    /**
     * Resolves deleteNode()'s fallback when no strategy is passed
     * explicitly: the relationship's own declared default, then the
     * project-wide config default.
     */
    private function deletionStrategy(LifecycleAware $model): DeletionStrategy
    {
        $rule = $this->selfReferentialRule($model);
        $value = $rule['deletion_strategy'] ?? config('lifecycle.defaults.self_referential.deletion_strategy');

        return DeletionStrategy::from((string) $value);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function eachChild(LifecycleAware&Model $model, array $rule, \Closure $callback, bool $withTrashed = false): void
    {
        $relationName = $rule['relation'] ?? null;

        if (! is_string($relationName)) {
            return;
        }

        $relation = $model->{$relationName}();

        if (! $relation instanceof Relation) {
            return;
        }

        $query = $withTrashed ? $relation->withTrashed() : $relation;

        $query->chunkById((int) config('lifecycle.chunk_size', 200), static function ($children) use ($callback): void {
            foreach ($children as $child) {
                $callback($child);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedRule(LifecycleAware $parent, string $ruleKey): array
    {
        $rule = $parent->lifecycleRules()[$ruleKey] ?? null;

        if (! is_array($rule)) {
            throw new InvalidArgumentException("Unknown lifecycle rule [{$ruleKey}] on ".$parent::class);
        }

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return BelongsToMany<Model, Model>|null
     */
    private function sharedRelation(LifecycleAware&Model $parent, array $rule): ?BelongsToMany
    {
        $relationName = $rule['relation'] ?? null;

        if (! is_string($relationName)) {
            return null;
        }

        $relation = $parent->{$relationName}();

        return $relation instanceof BelongsToMany ? $relation : null;
    }

    /**
     * Severs every currently-linked child from a shared relationship,
     * as a cascade of the parent's own deactivate/delete - never
     * blockable (see severSharedLink()), since the parent's own action
     * must be able to complete.
     *
     * @param  array<string, mixed>  $rule
     */
    private function cascadeSeverShared(LifecycleAware&Model $parent, array $rule, bool $hard): void
    {
        $relation = $this->sharedRelation($parent, $rule);

        if (! $relation instanceof BelongsToMany) {
            return;
        }

        foreach ($relation->get() as $child) {
            $this->severSharedLink($parent, $rule, $child, allowBlocking: false, hard: $hard);
        }
    }

    /**
     * Severs one child's pivot link and, if that was its last remaining
     * link for this relationship, applies the orphan_strategy.
     * 'prevent_removal' throws only when $allowBlocking is true (a
     * direct unlink() call) - a cascade from the parent's own
     * deactivate/delete falls back to 'unassigned' instead, since the
     * parent's own action can't be blocked by a downstream orphan.
     *
     * @param  array<string, mixed>  $rule
     */
    private function severSharedLink(LifecycleAware&Model $parent, array $rule, Model $child, bool $allowBlocking, bool $hard = false): void
    {
        $strategy = $this->orphanStrategy($rule);
        $wouldOrphan = $this->remainingLinks($child, $rule) <= 1;

        if ($wouldOrphan && $strategy === OrphanStrategy::PreventRemoval) {
            if ($allowBlocking) {
                throw OrphanRemovalBlockedException::make($child, (string) ($rule['relation'] ?? ''));
            }

            $strategy = OrphanStrategy::Unassigned;
        }

        $this->severPivot($parent, $rule, $child, $hard);
        $this->recordAffected($child);

        if ($wouldOrphan) {
            $this->applyOrphanConsequence($child, $strategy);
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function severPivot(LifecycleAware&Model $parent, array $rule, Model $child, bool $hard): void
    {
        $relation = $this->sharedRelation($parent, $rule);

        if (! $relation instanceof BelongsToMany) {
            return;
        }

        $pivotQuery = $relation->newPivotStatementForId($child->getKey())->whereNull('deleted_at');

        // A force-deleted parent's row is removed immediately (see
        // cascadeDeleted()'s docblock) - a merely soft-removed pivot row
        // would still reference it and violate the FK restrict
        // constraint, so this case must hard-delete the pivot row
        // instead of soft-removing it.
        $hard ? $pivotQuery->delete() : $pivotQuery->update(['deleted_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function remainingLinks(Model $child, array $rule): int
    {
        $inverseRelationName = $rule['inverse_relation'] ?? null;

        if (! is_string($inverseRelationName)) {
            return 0;
        }

        $relation = $child->{$inverseRelationName}();

        return $relation instanceof BelongsToMany ? $relation->count() : 0;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function orphanStrategy(array $rule): OrphanStrategy
    {
        $value = $rule['orphan_strategy'] ?? config('lifecycle.defaults.shared.orphan_strategy');

        return OrphanStrategy::from((string) $value);
    }

    private function applyOrphanConsequence(Model $child, OrphanStrategy $strategy): void
    {
        match ($strategy) {
            OrphanStrategy::AutoArchive => $child->forceFill(['lifecycle_status' => LifecycleStatus::Archived])->save(),
            OrphanStrategy::Unassigned => $child->forceFill(['lifecycle_orphaned_at' => now()])->save(),
            OrphanStrategy::PreventRemoval => throw new LogicException(
                'Unreachable: prevent_removal must be resolved to another strategy before applyOrphanConsequence() is called.'
            ),
        };
    }
}
