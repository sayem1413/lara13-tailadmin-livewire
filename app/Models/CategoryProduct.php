<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A real pivot model, not a bare array pivot, so severing a category-
 * product link is soft (auditable, restorable) rather than a delete -
 * see App\Models\Category::lifecycleRules() for why this isn't a
 * lifecycle `shared` relationship, and ProductService::syncCategories()
 * for the code that enforces the pair's uniqueness among live rows.
 *
 * @property int $id
 * @property int $category_id
 * @property int $product_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class CategoryProduct extends Pivot
{
    use SoftDeletes;

    protected $table = 'category_product';

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
