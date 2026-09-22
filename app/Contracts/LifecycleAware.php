<?php

namespace App\Contracts;

/**
 * Implemented by any Eloquent model that uses HasLifecycleIntegrity to
 * declare how its related records must behave when its own status
 * changes (see LifecycleIntegrityService and docs/lifecycle-integrity.md).
 * Extends LifecycleStatusAware because a model with relationship rules
 * always also needs activate()/deactivate() (from HasActiveStatus) - use
 * both traits together.
 */
interface LifecycleAware extends LifecycleStatusAware
{
    /**
     * One entry per governed relationship, keyed by an arbitrary rule
     * name. A single model can declare both directions - what it owns
     * ("downward") and what owns it ("upward") - as separate entries.
     * Each entry's shape depends on its `type` and which keys it sets:
     *
     * - exclusive / hierarchical, downward (this model cascades to a
     *   relation it owns): ['type', 'relation' (a hasMany/hasOne/
     *   morphMany method name), 'cascade' (list of 'activate'|
     *   'deactivate'|'delete', optional, defaults to ['deactivate',
     *   'delete'] - 'activate' is opt-in only, see cascadeActivated()),
     *   'restore_strategy' (optional, defaults to config), 'retain'
     *   (optional bool, default false - blocks a force-delete cascade
     *   from reaching this relationship's children at all, throwing
     *   RetainedRecordException; for historical/financial/audit records
     *   that must never be permanently destroyable, see Core Business
     *   Rule 2/6)].
     * - exclusive / hierarchical, upward (this model is guarded against
     *   its own owner): ['type', 'parent_relation' (a belongsTo method
     *   name), 'retain' (optional bool - this model itself must never be
     *   force-deleted, directly or via cascade)]. A model can declare
     *   this alongside its own downward entries to chain into a
     *   multi-level hierarchy - the ancestor walk follows parent_relation
     *   as far as it's declared.
     * - shared: ['type', 'relation' (a belongsToMany/morphToMany method
     *   name), 'orphan_strategy' (optional, defaults to config)].
     * - self_referential: ['type', 'parent_relation' (optional, defaults
     *   to 'parent'), 'deletion_strategy' (optional, defaults to config,
     *   overridable per-call - see App\Enums\DeletionStrategy for all
     *   three: promote_children, delete_subtree,
     *   block_if_children_exist), 'cascade' (optional, only meaningful
     *   value is ['activate'] - opts a self-referential subtree into
     *   cascading activation the same way it already always cascades
     *   deactivate/delete), 'retain' (optional bool, same meaning as the
     *   downward key above, applied to this relationship's children)].
     *
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleRules(): array;

    /**
     * The following three are provided automatically by
     * Illuminate\Database\Eloquent\SoftDeletes, which every model using
     * this module's traits must also use (see docs/lifecycle-integrity.md).
     * Declared without a return type here because SoftDeletes itself
     * doesn't declare one on any of them - requiring one in this
     * interface would be a fatal "must be compatible with" error for
     * any class composing both.
     */
    /**
     * @return bool
     */
    public function isForceDeleting();

    /**
     * @return bool
     */
    public function trashed();

    /**
     * @return bool
     */
    public function restore();
}
