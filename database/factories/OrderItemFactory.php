<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'size' => 'M',
            'quantity' => fake()->numberBetween(1, 3),
            'price_at_purchase' => fake()->randomFloat(2, 40, 150),
            'custom_player_name' => null,
            'custom_player_number' => null,
        ];
    }
}
