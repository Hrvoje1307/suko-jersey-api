<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\FakesCheckout;
use Tests\TestCase;

class OrderPublicTest extends TestCase
{
    use FakesCheckout;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeCheckout();
    }

    /**
     * @param  array<int, array{product_id: int, size: string, quantity: int}>  $items
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

        $product = Product::factory()->create(['price' => 79.50, 'sizes' => ['S', 'M', 'L']]);

        $response = $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'size' => 'M', 'quantity' => 2],
        ]));

        $response->assertCreated()
            ->assertJsonStructure(['order_reference', 'total_price', 'status', 'payment_status', 'stripe_checkout_url'])
            ->assertJsonPath('status', 'ordered')
            // Narudžba nastaje neplaćena — plaćanje potvrđuje tek webhook.
            ->assertJsonPath('payment_status', 'unpaid')
            ->assertJsonPath('total_price', 159);

        $this->assertMatchesRegularExpression('/^ORD-[A-Z0-9]{5}$/', $response->json('order_reference'));

        $order = Order::first();
        $this->assertSame('HR', $order->shipping_country);
        // Naziv se čita kroz proizvod, a veličina je snapshot na stavci.
        $this->assertSame($product->name, $order->items->first()->product->name);
        $this->assertSame('M', $order->items->first()->size);
        $this->assertSame('79.50', $order->items->first()->price_at_purchase);
        $this->assertSame('kupac@example.com', $order->customer->email);
        $this->assertSame('Ivan Horvat', $order->customer->name);

        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame('cs_test_'.$order->order_reference, $order->stripe_checkout_session_id);
        $this->assertSame(
            'https://checkout.stripe.com/c/pay/cs_test_'.$order->order_reference,
            $response->json('stripe_checkout_url'),
        );

        // Potvrda narudžbe ide tek kad Stripe javi da je plaćeno.
        Notification::assertNothingSent();
    }

    public function test_returns_502_when_checkout_session_cannot_be_opened(): void
    {
        Notification::fake();
        $this->checkout->shouldFail = true;

        $product = Product::factory()->create();

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'size' => 'M', 'quantity' => 1],
        ]))->assertStatus(502);

        // Narudžba ostaje kao trag pokušaja, ali neplaćena i bez sesije.
        $order = Order::sole();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertNull($order->stripe_checkout_session_id);
        Notification::assertNothingSent();
    }

    public function test_any_listed_size_can_be_ordered_in_any_quantity(): void
    {
        Notification::fake();

        // Zalihe nema — navedena veličina je uvijek dostupna, bez obzira na količinu.
        $product = Product::factory()->sizes(['S', 'M'])->create();

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'size' => 'S', 'quantity' => 50],
        ]))->assertCreated();
    }

    public function test_rejects_size_the_product_does_not_offer(): void
    {
        Notification::fake();

        $product = Product::factory()->sizes(['S', 'M'])->create();

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'size' => 'XXL', 'quantity' => 1],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.size');

        $this->assertDatabaseCount('orders', 0);
        Notification::assertNothingSent();
    }

    public function test_rejects_unknown_product(): void
    {
        $this->postJson('/api/orders', $this->payload([
            ['product_id' => 999, 'size' => 'M', 'quantity' => 1],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_rejects_draft_product(): void
    {
        Notification::fake();

        // Draft nije javno vidljiv u katalogu, pa se ne smije ni naručiti.
        $product = Product::factory()->draft()->create();

        $this->postJson('/api/orders', $this->payload([
            ['product_id' => $product->id, 'size' => 'M', 'quantity' => 1],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_requires_customer_and_shipping_address(): void
    {
        $this->postJson('/api/orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer', 'items', 'shipping_address']);
    }

    public function test_lookup_returns_status_without_tracking_before_shipping(): void
    {
        $order = Order::factory()->paid()->create([
            'status' => OrderStatus::SentToSupplier,
            'tracking_number_internal' => 'HR123456789',
        ]);

        $this->getJson('/api/orders/lookup?order_reference='.$order->order_reference.'&email='.$order->customer->email)
            ->assertOk()
            ->assertJsonPath('order_reference', $order->order_reference)
            ->assertJsonPath('status_label', 'Naručeno kod dobavljača')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('tracking_number', null)
            ->assertJsonPath('estimated_delivery', $order->created_at->copy()->addDays(21)->toDateString());
    }

    public function test_lookup_exposes_tracking_once_shipped(): void
    {
        $order = Order::factory()->paid()->create([
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

    public function test_lookup_hides_fulfillment_label_until_payment_lands(): void
    {
        // status='ordered' + unpaid: kupcu se ne smije reći "Narudžba
        // zaprimljena" dok plaćanje nije prošlo.
        $order = Order::factory()->create(['status' => OrderStatus::Ordered]);

        $this->getJson('/api/orders/lookup?order_reference='.$order->order_reference.'&email='.$order->customer->email)
            ->assertOk()
            ->assertJsonPath('payment_status', 'unpaid')
            ->assertJsonPath('status_label', 'Čeka plaćanje');
    }

    public function test_payment_status_lookup_by_reference_alone(): void
    {
        $order = Order::factory()->paid()->create(['status' => OrderStatus::Ordered]);

        $this->getJson('/api/orders/'.$order->order_reference.'/status')
            ->assertOk()
            ->assertExactJson([
                'order_reference' => $order->order_reference,
                'payment_status' => 'paid',
                'status_label' => 'Narudžba zaprimljena',
            ]);
    }

    public function test_payment_status_reports_pending_before_webhook(): void
    {
        $order = Order::factory()->create();

        $this->getJson('/api/orders/'.$order->order_reference.'/status')
            ->assertOk()
            ->assertJsonPath('payment_status', 'unpaid')
            ->assertJsonPath('status_label', 'Čeka plaćanje');
    }

    public function test_payment_status_never_leaks_tracking_or_customer(): void
    {
        $order = Order::factory()->paid()->create([
            'status' => OrderStatus::Shipped,
            'tracking_number_internal' => 'HR123456789',
        ]);

        // Ruta je pogodiva samom referencom, pa ne smije otkriti ništa osobno.
        $this->getJson('/api/orders/'.$order->order_reference.'/status')
            ->assertOk()
            ->assertJsonMissing(['tracking_number' => 'HR123456789'])
            ->assertJsonMissingPath('estimated_delivery')
            ->assertJsonMissingPath('total_price');
    }

    public function test_payment_status_404_for_unknown_reference(): void
    {
        $this->getJson('/api/orders/ORD-NEMA/status')->assertNotFound();
    }
}
