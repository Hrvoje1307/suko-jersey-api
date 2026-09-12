<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderPublicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array{product_variant_id: int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    protected function payload(array $items): array
    {
        return [
            'customer' => [
                'email' => 'kupac@example.com',
                'name' => 'Ivan Horvat',
                'phone' => '+385911234567',
            ],
            'items' => $items,
            'shipping_address' => [
                'line1' => 'Ilica 1',
                'city' => 'Zagreb',
                'postal_code' => '10000',
                'country' => 'hr',
            ],
        ];
    }

    public function test_creates_order_and_returns_confirmation(): void
    {
        Notification::fake();

        $product = Product::factory()->create(['price' => 79.50]);
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock_quantity' => 5]);

        $response = $this->postJson('/api/orders', $this->payload([
            ['product_variant_id' => $variant->id, 'quantity' => 2],
        ]));

        $response->assertCreated()
            ->assertJsonStructure(['order_reference', 'total_price', 'status'])
            ->assertJsonPath('status', 'ordered')
            ->assertJsonPath('total_price', 159);

        $this->assertMatchesRegularExpression('/^ORD-[A-Z0-9]{5}$/', $response->json('order_reference'));

        $order = Order::first();
        $this->assertSame('HR', $order->shipping_country);
        // Stavka više ne drži snapshot — naziv i veličina se čitaju kroz varijantu.
        $this->assertSame($product->name, $order->items->first()->variant->product->name);
        $this->assertSame('M', $order->items->first()->variant->size);
        $this->assertSame('79.50', $order->items->first()->price_at_purchase);
        $this->assertSame('kupac@example.com', $order->customer->email);
        $this->assertSame('Ivan Horvat', $order->customer->name);

        Notification::assertSentOnDemand(OrderPlaced::class);
    }

    public function test_stock_is_not_decremented(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);

        $this->postJson('/api/orders', $this->payload([
            ['product_variant_id' => $variant->id, 'quantity' => 3],
        ]))->assertCreated();

        $this->assertSame(5, $variant->fresh()->stock_quantity);
    }

    public function test_rejects_order_exceeding_stock(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create(['size' => 'S', 'stock_quantity' => 1]);

        $this->postJson('/api/orders', $this->payload([
            ['product_variant_id' => $variant->id, 'quantity' => 2],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('orders', 0);
        Notification::assertNothingSent();
    }

    public function test_rejects_unknown_variant(): void
    {
        $this->postJson('/api/orders', $this->payload([
            ['product_variant_id' => 999, 'quantity' => 1],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_variant_id');
    }

    public function test_requires_customer_and_shipping_address(): void
    {
        $this->postJson('/api/orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer', 'items', 'shipping_address']);
    }

    public function test_lookup_returns_status_without_tracking_before_shipping(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::SentToSupplier,
            'tracking_number_internal' => 'HR123456789',
        ]);

        $this->getJson('/api/orders/lookup?order_reference='.$order->order_reference.'&email='.$order->customer->email)
            ->assertOk()
            ->assertJsonPath('order_reference', $order->order_reference)
            ->assertJsonPath('status_label', 'Naručeno kod dobavljača')
            ->assertJsonPath('tracking_number', null)
            ->assertJsonPath('estimated_delivery', $order->created_at->copy()->addDays(21)->toDateString());
    }

    public function test_lookup_exposes_tracking_once_shipped(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Shipped,
            'tracking_number_internal' => 'HR123456789',
        ]);

        $this->getJson('/api/orders/lookup?order_reference='.$order->order_reference.'&email='.$order->customer->email)
            ->assertOk()
            ->assertJsonPath('tracking_number', 'HR123456789');
    }

    public function test_lookup_requires_matching_email(): void
    {
        $order = Order::factory()->create();

        $this->getJson('/api/orders/lookup?order_reference='.$order->order_reference.'&email=netko@drugi.com')
            ->assertNotFound();
    }

    public function test_lookup_requires_parameters(): void
    {
        $this->getJson('/api/orders/lookup')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_reference', 'email']);
    }
}
