<?php

namespace Database\Factories;

use App\Enums\LifecycleStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Services\Inventory\StockService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word().' '.fake()->word();

        return [
            'sku' => strtoupper(Str::random(8)),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'price' => fake()->randomFloat(2, 10, 5000),
        ];
    }

    public function inactive(): static
    {
        return $this->afterCreating(fn (Product $product) => $product->deactivate());
    }

    public function archived(): static
    {
        return $this->afterCreating(
            fn (Product $product) => $product->forceFill(['lifecycle_status' => LifecycleStatus::Archived])->save()
        );
    }

    /**
     * Routes the initial stock through StockService rather than setting
     * stock_quantity directly, so the stock_quantity === SUM(movements)
     * invariant holds in tests that use this state, same as in the app.
     */
    public function withStock(int $quantity): static
    {
        return $this->afterCreating(
            fn (Product $product) => app(StockService::class)->adjust($product, $quantity, StockMovementType::Initial)
        );
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'low_stock_threshold' => 10,
        ])->withStock(5);
    }
}
