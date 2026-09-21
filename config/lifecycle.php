<?php

use App\Enums\DeletionStrategy;
use App\Enums\OrphanStrategy;
use App\Enums\RestoreStrategy;

/*
|--------------------------------------------------------------------------
| Entity Lifecycle & Relationship Integrity
|--------------------------------------------------------------------------
|
| Project-wide defaults for every model that uses HasLifecycleIntegrity.
| A model's own lifecycleRules() can override any of the "defaults" keys
| per relationship. See docs/lifecycle-integrity.md for the full guide.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk Cascade Threshold
    |--------------------------------------------------------------------------
    |
    | A cascade whose estimated affected-row count is below this number
    | runs synchronously, inside the triggering request's transaction. At
    | or above it, LifecycleIntegrityService dispatches a queued,
    | chunked Bus::batch() instead (see "queue" below).
    |
    */
    'bulk_threshold' => (int) env('LIFECYCLE_BULK_THRESHOLD', 500),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | Row count per chunkById() page, used both for the synchronous path
    | and for each queued job in a bulk cascade's batch.
    |
    */
    'chunk_size' => (int) env('LIFECYCLE_CHUNK_SIZE', 200),

    /*
    |--------------------------------------------------------------------------
    | Per-Relationship-Type Defaults
    |--------------------------------------------------------------------------
    |
    | Used whenever a model's lifecycleRules() entry omits the
    | corresponding strategy key.
    |
    */
    'defaults' => [
        'exclusive' => [
            // 'cascade' (immediately restore children) or
            // 'pending_activation' (safer default - children need a
            // separate, explicit activation after the parent is restored).
            'restore_strategy' => RestoreStrategy::PendingActivation->value,
        ],
        'shared' => [
            // 'auto_archive', 'unassigned', or 'prevent_removal' (safest
            // default - never silently orphans a child).
            'orphan_strategy' => OrphanStrategy::PreventRemoval->value,
        ],
        'self_referential' => [
            // Fallback only - callers are expected to choose explicitly
            // per LifecycleIntegrityService::deleteNode() call.
            'deletion_strategy' => DeletionStrategy::DeleteSubtree->value,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Connection/queue used for CascadeLifecycleActionJob when a cascade
    | crosses the bulk_threshold. A null connection uses the app's default
    | queue connection.
    |
    */
    'queue' => [
        'connection' => env('LIFECYCLE_QUEUE_CONNECTION'),
        'queue' => env('LIFECYCLE_QUEUE_NAME', 'lifecycle'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    |
    | Whether cascade actions write a grouped entry to the existing
    | spatie/activitylog log (one entry per triggering action, with
    | affected record counts/ids in its properties for drill-down).
    |
    */
    'activity_log' => [
        'enabled' => true,
    ],

];
