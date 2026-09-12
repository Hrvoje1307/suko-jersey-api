<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPlayer>
 */
class ProductPlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'player_name' => fake()->lastName(),
            'player_number' => (string) fake()->numberBetween(1, 99),
            'sort_order' => 0,
        ];
    }
}
