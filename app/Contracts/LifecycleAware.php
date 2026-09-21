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
     *   morphMany method name), 'cascade' (list of 'deactivate'|'delete',
     *   optional, defaults to both), 'restore_strategy' (optional,
     *   defaults to config)].
     * - exclusive / hierarchical, upward (this model is guarded against
     *   its own owner): ['type', 'parent_relation' (a belongsTo method
     *   name)]. A model can declare this alongside its own downward
     *   entries to chain into a multi-level hierarchy - the ancestor
     *   walk follows parent_relation as far as it's declared.
     * - shared: ['type', 'relation' (a belongsToMany/morphToMany method
     *   name), 'orphan_strategy' (optional, defaults to config)].
     * - self_referential: ['type', 'parent_relation' (optional, defaults
     *   to 'parent'), 'deletion_strategy' (optional, defaults to config,
     *   overridable per-call)].
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
