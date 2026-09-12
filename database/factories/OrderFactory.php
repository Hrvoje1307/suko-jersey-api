<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
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
            'customer_id' => Customer::factory(),
            'shipping_address_line1' => fake()->streetAddress(),
            'shipping_address_line2' => null,
            'shipping_city' => fake()->city(),
            'shipping_postal_code' => substr(fake()->postcode(), 0, 20),
            'shipping_country' => 'HR',
            'status' => OrderStatus::Ordered,
            'total_price' => fake()->randomFloat(2, 40, 300),
            'tracking_number_internal' => null,
        ];
    }
}
