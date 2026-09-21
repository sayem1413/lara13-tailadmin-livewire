<?php

namespace App\Services\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the shared `lifecycle_closures` table (see the
 * create_lifecycle_closures_table migration) for every self-referential
 * model using HasLifecycleIntegrity - one polymorphic table rather than
 * a table per model. Gives O(1) indexed reads for "is X a descendant of
 * Y" (cycle prevention) and "collect X's whole subtree", at the cost of
 * write complexity on every reparent - see docs/lifecycle-integrity.md
 * for the trade-off this was chosen over recursive CTEs for.
 */
class ClosureTableManager
{
    private const TABLE = 'lifecycle_closures';

    /**
     * Brings the closure table in sync with $node's current parent_id -
     * safe to call whether $node is brand new (only its self-row exists
     * yet) or being re-parented (it may already have its own subtree).
     * Idempotent: safe to call more than once for the same state.
     */
    public function reparent(Model $node, ?Model $newParent): void
    {
        $type = $node::class;
        $now = now();

        DB::table(self::TABLE)->insertOrIgnore([[
            'closureable_type' => $type,
            'ancestor_id' => $node->getKey(),
            'descendant_id' => $node->getKey(),
            'depth' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);

        // $node's own subtree (itself + every existing descendant).
        $subtreeIds = DB::table(self::TABLE)
            ->where('closureable_type', $type)
            ->where('ancestor_id', $node->getKey())
            ->pluck('descendant_id')
            ->all();

        // Drop links from anything outside the subtree into it - these
        // reflect the OLD position and no longer apply once $node moves.
        // Links entirely within the subtree (node -> its own descendants)
        // are untouched, since re-parenting doesn't change those.
        DB::table(self::TABLE)
            ->where('closureable_type', $type)
            ->whereIn('descendant_id', $subtreeIds)
            ->whereNotIn('ancestor_id', $subtreeIds)
            ->delete();

        if ($newParent === null) {
            return;
        }

        $depthsWithinSubtree = DB::table(self::TABLE)
            ->where('closureable_type', $type)
            ->where('ancestor_id', $node->getKey())
            ->pluck('depth', 'descendant_id');

        $ancestorsOfNewParent = DB::table(self::TABLE)
            ->where('closureable_type', $type)
            ->where('descendant_id', $newParent->getKey())
            ->get(['ancestor_id', 'depth']);

        $rows = [];

        foreach ($ancestorsOfNewParent as $ancestor) {
            foreach ($depthsWithinSubtree as $descendantId => $depthFromNode) {
                $rows[] = [
                    'closureable_type' => $type,
                    'ancestor_id' => $ancestor->ancestor_id,
                    'descendant_id' => $descendantId,
                    'depth' => $ancestor->depth + 1 + $depthFromNode,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table(self::TABLE)->insertOrIgnore($rows);
        }
    }

    /**
     * Removes every closure row for this one node (as either ancestor or
     * descendant) - called when a node is force-deleted. Not recursive:
     * each node's own force-delete cleans up only its own rows, so a
     * subtree cascade cleans itself up one node at a time as each child
     * is force-deleted in turn.
     */
    public function forget(Model $node): void
    {
        DB::table(self::TABLE)
            ->where('closureable_type', $node::class)
            ->where(function ($query) use ($node): void {
                $query->where('ancestor_id', $node->getKey())
                    ->orWhere('descendant_id', $node->getKey());
            })
            ->delete();
    }

    /**
     * True if $node is anywhere in $possibleAncestor's subtree - used to
     * block a re-parent that would make a node its own ancestor.
     */
    public function isDescendantOf(Model $node, Model $possibleAncestor): bool
    {
        return DB::table(self::TABLE)
            ->where('closureable_type', $node::class)
            ->where('ancestor_id', $possibleAncestor->getKey())
            ->where('descendant_id', $node->getKey())
            ->exists();
    }
}
