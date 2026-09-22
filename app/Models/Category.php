<?php

namespace App\Models;

use App\Contracts\Exportable;
use App\Contracts\Importable;
use App\Contracts\LifecycleAware;
use App\Enums\DeletionStrategy;
use App\Enums\LifecycleStatus;
use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\HasLifecycleIntegrity;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property LifecycleStatus $lifecycle_status
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $lifecycle_orphaned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['parent_id', 'name', 'slug', 'description', 'sort_order'])]
class Category extends Model implements Exportable, Importable, LifecycleAware
{
    /** @use HasFactory<CategoryFactory> */
    use HasActiveStatus, HasFactory, HasLifecycleIntegrity, LogsActivity, SoftDeletes;

    protected $attributes = [
        'lifecycle_status' => 'active',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'lifecycle_status' => LifecycleStatus::class,
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'lifecycle_orphaned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Product, $this, CategoryProduct>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(CategoryProduct::class)
            ->withPivot(['created_by'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * Declares two rules for the same parent/child relationship - see
     * docs/lifecycle-integrity.md and the Phase 1 plan for the reasoning:
     *
     * - `children` (self_referential) gets cycle prevention, subtree
     *   cascade, and a choice of deletion strategy for free.
     * - `parent` (hierarchical, upward-only - no `relation` key, so it
     *   adds no second cascade) exists purely to restore the ancestor
     *   guard (isEffectivelyActive(), "can't activate a child under an
     *   inactive parent") that a bare self_referential rule doesn't get
     *   on its own, since ancestors()/upwardRules() only look at
     *   exclusive/hierarchical-typed rules.
     *
     * Deliberately does NOT declare a `shared` rule for the products()
     * relationship - see App\Models\CategoryProduct and ProductService.
     *
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleRules(): array
    {
        return [
            'children' => [
                'type' => 'self_referential',
                'relation' => 'children',
                'parent_relation' => 'parent',
                'deletion_strategy' => DeletionStrategy::BlockIfChildrenExist->value,
                // Opt-in: reactivating a branch brings its subtree back
                // with it, mirroring how deactivating it always does.
                'cascade' => ['activate'],
            ],
            'parent' => [
                'type' => 'hierarchical',
                'parent_relation' => 'parent',
            ],
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'parent_id', 'description', 'sort_order', 'lifecycle_status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    public static function exportColumns(): array
    {
        return [
            'name' => 'Name',
            'slug' => 'Slug',
            'parent.name' => 'Parent',
            // GenericExport reads columns via data_get() - ".value"
            // resolves the enum's backing value instead of handing
            // PhpSpreadsheet a BackedEnum object.
            'lifecycle_status.value' => 'Status',
            'products_count' => 'Products',
            'created_at' => 'Created At',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function importColumns(): array
    {
        return [
            'name' => 'Name',
            'slug' => 'Slug',
            'parent_slug' => 'Parent Slug',
            'description' => 'Description',
            'sort_order' => 'Sort Order',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function importRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:categories,slug'],
            'parent_slug' => ['nullable', 'string', 'exists:categories,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromImportRow(array $row): static
    {
        $parentId = filled($row['parent_slug'] ?? null)
            ? static::query()->where('slug', $row['parent_slug'])->value('id')
            : null;

        return static::query()->make([
            'parent_id' => $parentId,
            'name' => $row['name'],
            'slug' => filled($row['slug'] ?? null) ? $row['slug'] : Str::slug($row['name']),
            'description' => $row['description'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ]);
    }
}
