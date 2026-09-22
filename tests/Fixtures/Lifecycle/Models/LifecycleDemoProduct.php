<?php

namespace Tests\Fixtures\Lifecycle\Models;

use App\Enums\LifecycleStatus;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixture for the Entity Lifecycle module's own test suite - the
 * "grouped" (child) side of the Category <-> Product shared example
 * (see docs/lifecycle-integrity.md). Only needs HasActiveStatus (for the
 * auto_archive/deactivate-style writes the orphan strategies make) - it
 * doesn't declare lifecycleRules() of its own, since Category already
 * declares the relationship and its inverse_relation.
 */
#[Fillable(['name', 'lifecycle_status', 'activated_at', 'deactivated_at', 'lifecycle_orphaned_at'])]
class LifecycleDemoProduct extends Model
{
    use HasActiveStatus, SoftDeletes;

    protected $table = 'lifecycle_demo_products';

    protected $attributes = [
        'lifecycle_status' => 'active',
    ];

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
     * @return BelongsToMany<LifecycleDemoCategory, $this, LifecycleDemoCategoryProduct>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(LifecycleDemoCategory::class, 'lifecycle_demo_category_product')
            ->using(LifecycleDemoCategoryProduct::class)
            ->withPivot(['created_by'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }
}
