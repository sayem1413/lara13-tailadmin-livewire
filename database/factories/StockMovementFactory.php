<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $change = fake()->numberBetween(-10, 50);

        return [
            'product_id' => Product::factory(),
            'type' => $change >= 0 ? StockMovementType::Purchase : StockMovementType::Sale,
            'quantity_change' => $change,
            'quantity_after' => max(0, $change),
        ];
    }
}
