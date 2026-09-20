<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
            'payment_status' => PaymentStatus::Unpaid,
            'total_price' => fake()->randomFloat(2, 40, 300),
            'tracking_number_internal' => null,
            'stripe_checkout_session_id' => null,
            'stripe_payment_intent_id' => null,
        ];
    }

    /**
     * Plaćena narudžba — stanje u kojem je stvarno vidljiva kupcu i adminu.
     */
    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_status' => PaymentStatus::Paid,
            'stripe_checkout_session_id' => 'cs_test_'.Str::random(24),
            'stripe_payment_intent_id' => 'pi_test_'.Str::random(24),
        ]);
    }
}
