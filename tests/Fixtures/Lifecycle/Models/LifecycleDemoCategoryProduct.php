<?php

namespace Tests\Fixtures\Lifecycle\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Custom pivot for the shared (many-to-many) Category <-> Product
 * fixture - see docs/lifecycle-integrity.md. A shared relationship's
 * pivot must be a model like this one (not a bare array pivot) so a
 * severed link can be soft-removed rather than hard-deleted, and so
 * `created_by` is auditable.
 */
class LifecycleDemoCategoryProduct extends Pivot
{
    use SoftDeletes;

    protected $table = 'lifecycle_demo_category_product';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
