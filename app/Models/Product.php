<?php

namespace App\Models;

use App\Contracts\Exportable;
use App\Contracts\Importable;
use App\Enums\LifecycleStatus;
use App\Enums\ProductUnit;
use App\Models\Concerns\HasActiveStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $sku
 * @property string|null $barcode
 * @property string $name
 * @property string $slug
 * @property ProductUnit $unit
 * @property string|null $short_description
 * @property string|null $description
 * @property float $price
 * @property float|null $compare_at_price
 * @property float|null $cost_price
 * @property float|null $tax_rate
 * @property bool $track_inventory
 * @property int $stock_quantity
 * @property int|null $low_stock_threshold
 * @property float|null $weight
 * @property LifecycleStatus $lifecycle_status
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $lifecycle_orphaned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'sku', 'barcode', 'name', 'slug', 'unit', 'short_description', 'description',
    'price', 'compare_at_price', 'cost_price', 'tax_rate',
    'track_inventory', 'low_stock_threshold', 'weight',
])]
class Product extends Model implements Exportable, HasMedia, Importable
{
    /** @use HasFactory<ProductFactory> */
    use HasActiveStatus, HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $attributes = [
        'lifecycle_status' => 'active',
        'track_inventory' => true,
        'stock_quantity' => 0,
        'unit' => 'pc',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'weight' => 'decimal:3',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'unit' => ProductUnit::class,
            'lifecycle_status' => LifecycleStatus::class,
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'lifecycle_orphaned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Category, $this, CategoryProduct>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->using(CategoryProduct::class)
            ->withPivot(['created_by'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function isSellable(): bool
    {
        return $this->lifecycle_status === LifecycleStatus::Active;
    }

    public function isLowStock(): bool
    {
        if (! $this->track_inventory) {
            return false;
        }

        $threshold = $this->low_stock_threshold ?? (int) setting('low_stock_threshold', 10);

        return $this->stock_quantity <= $threshold;
    }

    public function effectiveTaxRate(): float
    {
        return (float) ($this->tax_rate ?? setting('tax_rate', 0));
    }

    public function registerMediaCollections(): void
    {
        // Several images, unlike User's single-file avatar collection.
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')->fit(Fit::Contain, 300, 300);
    }

    public function primaryImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('images', 'thumbnail') ?: null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sku', 'name', 'slug', 'unit', 'price', 'cost_price', 'tax_rate', 'track_inventory', 'stock_quantity', 'lifecycle_status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    public static function exportColumns(): array
    {
        return [
            'sku' => 'SKU',
            'barcode' => 'Barcode',
            'name' => 'Name',
            'unit.value' => 'Unit',
            'price' => 'Price',
            'stock_quantity' => 'Stock',
            'lifecycle_status.value' => 'Status',
            'category_list' => 'Categories',
            'created_at' => 'Created At',
        ];
    }

    /**
     * A comma-separated list of category names for the export column
     * above - ProductService::filteredQuery() eager-loads `categories`
     * so this doesn't N+1 during a chunked export.
     */
    public function getCategoryListAttribute(): string
    {
        return $this->categories->pluck('name')->implode(', ');
    }

    /**
     * @return array<string, string>
     */
    public static function importColumns(): array
    {
        return [
            'sku' => 'Sku',
            'name' => 'Name',
            'unit' => 'Unit',
            'price' => 'Price',
            'cost_price' => 'Cost Price',
            'barcode' => 'Barcode',
            'short_description' => 'Short Description',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function importRules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'in:pc,kg,g,l,ml,dozen,packet,bag'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'short_description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Imported products land at zero stock and with no categories - a
     * plain unsaved instance can't write either through StockService or
     * ProductService::syncCategories(), and setting stock_quantity
     * directly here would break the stock_quantity === SUM(movements)
     * invariant. Both are set afterwards through the normal UI.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromImportRow(array $row): static
    {
        return static::query()->make([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'unit' => $row['unit'] ?? 'pc',
            'price' => $row['price'],
            'cost_price' => $row['cost_price'] ?? null,
            'barcode' => $row['barcode'] ?? null,
            'short_description' => $row['short_description'] ?? null,
        ]);
    }
}
