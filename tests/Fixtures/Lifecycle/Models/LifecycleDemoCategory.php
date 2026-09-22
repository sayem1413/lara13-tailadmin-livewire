<?php

namespace Tests\Fixtures\Lifecycle\Models;

use App\Contracts\LifecycleAware;
use App\Enums\LifecycleStatus;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasLifecycleIntegrity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixture for the Entity Lifecycle module's own test suite - the
 * "grouping" side of the Category <-> Product shared (many-to-many)
 * example (see docs/lifecycle-integrity.md). Declares the relationship
 * once, from this side; Product doesn't need to declare anything back.
 */
#[Fillable(['name', 'lifecycle_status', 'activated_at', 'deactivated_at', 'lifecycle_orphaned_at'])]
class LifecycleDemoCategory extends Model implements LifecycleAware
{
    use HasActiveStatus, HasLifecycleIntegrity, SoftDeletes;

    protected $table = 'lifecycle_demo_categories';

    protected $attributes = [
        'lifecycle_status' => 'active',
    ];

    /**
     * Test-only knob so different tests can exercise all three orphan
     * strategies without three fixture pairs.
     */
    public static string $orphanStrategy = 'prevent_removal';

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
     * @return BelongsToMany<LifecycleDemoProduct, $this, LifecycleDemoCategoryProduct>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(LifecycleDemoProduct::class, 'lifecycle_demo_category_product')
            ->using(LifecycleDemoCategoryProduct::class)
            ->withPivot(['created_by'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleRules(): array
    {
        return [
            'products' => [
                'type' => 'shared',
                'relation' => 'products',
                'inverse_relation' => 'categories',
                'orphan_strategy' => static::$orphanStrategy,
            ],
        ];
    }
}
