<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_reference' => 'ORD-'.Str::upper(Str::random(5)),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'shipping_line1' => fake()->streetAddress(),
            'shipping_line2' => null,
            'shipping_city' => fake()->city(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country' => 'HR',
            'status' => OrderStatus::Ordered,
            'total_price' => fake()->randomFloat(2, 40, 300),
            'tracking_number_internal' => null,
        ];
    }
}
