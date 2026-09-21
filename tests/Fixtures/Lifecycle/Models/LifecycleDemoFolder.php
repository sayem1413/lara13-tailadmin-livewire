<?php

namespace Tests\Fixtures\Lifecycle\Models;

use App\Contracts\LifecycleAware;
use App\Enums\LifecycleStatus;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasLifecycleIntegrity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixture for the Entity Lifecycle module's own test suite - a
 * self-referential Folder-in-Folder example (see
 * docs/lifecycle-integrity.md, Part D).
 */
#[Fillable(['parent_id', 'name', 'lifecycle_status', 'activated_at', 'deactivated_at', 'lifecycle_orphaned_at'])]
class LifecycleDemoFolder extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    protected $table = 'lifecycle_demo_folders';

    protected $attributes = [
        'lifecycle_status' => 'active',
    ];

    /**
     * Test-only knob so different tests can exercise all three deletion
     * strategies without two fixture models.
     */
    public static string $deletionStrategy = 'delete_subtree';

    /**
     * Test-only knob so a test can opt the children relation into the
     * (off-by-default) 'activate' cascade trigger without a second
     * fixture.
     *
     * @var array<int, string>
     */
    public static array $childrenCascade = [];

    /**
     * Test-only knob so a test can mark the children relation `retain`
     * without a second fixture.
     */
    public static bool $childrenRetained = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lifecycle_status' => LifecycleStatus::class,
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'lifecycle_orphaned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LifecycleDemoFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<LifecycleDemoFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleRules(): array
    {
        return [
            'children' => [
                'type' => 'self_referential',
                'relation' => 'children',
                'parent_relation' => 'parent',
                'deletion_strategy' => static::$deletionStrategy,
                'cascade' => static::$childrenCascade,
                'retain' => static::$childrenRetained,
            ],
        ];
    }
}
