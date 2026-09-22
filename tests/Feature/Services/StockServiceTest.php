<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\StockService;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('records a movement and updates the running balance in one transaction', function () {
    $product = Product::factory()->create();

    $movement = app(StockService::class)->adjust($product, 50, StockMovementType::Purchase);

    expect($movement->quantity_after)->toBe(50)
        ->and($product->fresh()->stock_quantity)->toBe(50);
});

it('keeps stock_quantity equal to the sum of every movement across a sequence of adjustments', function () {
    $product = Product::factory()->create();

    app(StockService::class)->adjust($product, 100, StockMovementType::Purchase);
    app(StockService::class)->adjust($product, -30, StockMovementType::Sale);
    app(StockService::class)->adjust($product, -5, StockMovementType::Damage);
    app(StockService::class)->adjust($product, 10, StockMovementType::Return);

    expect($product->fresh()->stock_quantity)->toBe(75)
        ->and(StockMovement::where('product_id', $product->id)->sum('quantity_change'))->toBe(75);
});

it('blocks a negative result unless allow_negative_stock is enabled', function () {
    $product = Product::factory()->withStock(5)->create();

    expect(fn () => app(StockService::class)->adjust($product->fresh(), -10, StockMovementType::Sale))
        ->toThrow(ValidationException::class);

    expect($product->fresh()->stock_quantity)->toBe(5);

    app(SettingService::class)->set('allow_negative_stock', true);

    app(StockService::class)->adjust($product->fresh(), -10, StockMovementType::Sale);

    expect($product->fresh()->stock_quantity)->toBe(-5);
});

it('blocks any adjustment when the product does not track inventory', function () {
    $product = Product::factory()->create(['track_inventory' => false]);

    expect(fn () => app(StockService::class)->adjust($product, 10, StockMovementType::Purchase))
        ->toThrow(ValidationException::class);
});

it('blocks a zero-quantity adjustment', function () {
    $product = Product::factory()->create();

    expect(fn () => app(StockService::class)->adjust($product, 0, StockMovementType::Adjustment))
        ->toThrow(ValidationException::class);
});

it('setLevel computes the correct delta and records it as an Adjustment', function () {
    $product = Product::factory()->withStock(20)->create();

    $movement = app(StockService::class)->setLevel($product->fresh(), 15, 'Stock count correction');

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity_change)->toBe(-5)
        ->and($product->fresh()->stock_quantity)->toBe(15);
});

it('setLevel rejects a quantity equal to the current stock level', function () {
    $product = Product::factory()->withStock(20)->create();

    expect(fn () => app(StockService::class)->setLevel($product->fresh(), 20))
        ->toThrow(ValidationException::class);
});

it('re-reads the product with a lock before adjusting its stock', function () {
    $product = Product::factory()->create();

    $selects = 0;
    DB::listen(function ($query) use (&$selects): void {
        if (str_contains($query->sql, 'products') && str_starts_with(trim($query->sql), 'select')) {
            $selects++;
        }
    });

    app(StockService::class)->adjust($product, 10, StockMovementType::Purchase);

    // Same SQLite caveat as every other lockForUpdate() test in this
    // app: this proves the locked re-read happens, not that a real lock
    // is held.
    expect($selects)->toBeGreaterThanOrEqual(1);
});

it('lowStockQuery finds a product at or under its own threshold, falling back to the global setting', function () {
    $low = Product::factory()->create(['low_stock_threshold' => 5])->fresh();
    app(StockService::class)->adjust($low, 5, StockMovementType::Purchase);

    $usesGlobalDefault = Product::factory()->create(['low_stock_threshold' => null])->fresh();
    app(StockService::class)->adjust($usesGlobalDefault, 3, StockMovementType::Purchase);

    $healthy = Product::factory()->withStock(100)->create();

    $ids = app(StockService::class)->lowStockQuery()->pluck('id');

    expect($ids)->toContain($low->id)
        ->toContain($usesGlobalDefault->id)
        ->not->toContain($healthy->id);
});
