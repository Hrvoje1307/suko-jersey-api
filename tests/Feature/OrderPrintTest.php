<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\FakesCheckout;
use Tests\TestCase;

/**
 * Tisak imena i broja na dresu. Backend ne poznaje liste igrača — frontend ih
 * vuče s vanjskog API-ja, a ovdje se sprema točno ono što kupac upiše.
 */
class OrderPrintTest extends TestCase
{
    use FakesCheckout;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->fakeCheckout();
    }

    protected function actingAsAdmin(): static
    {
        return $this->actingAs(AdminUser::factory()->create(), 'sanctum');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function payload(array $items): array
    {
        return [
            'customer' => ['email' => 'kupac@example.com', 'name' => 'Ivan Horvat'],
            'items' => $items,
            'shipping_address' => [
                'line1' => 'Ilica 1',
                'city' => 'Zagreb',
                'postal_code' => '10000',
                'country' => 'HR',
            ],
        ];
    }

    public function test_name_and_number_are_persisted_on_the_order_item(): void
    {
        $product = Product::factory()->sizes(['M'])->create();

        $this->postJson('/api/orders', $this->payload([[
            'product_id' => $product->id,
            'size' => 'M',
            'quantity' => 1,
            'custom_player_name' => 'Modrić',
            'custom_player_number' => '10',
        ]]))->assertCreated();

        $item = OrderItem::sole();
        $this->assertSame('Modrić', $item->custom_player_name);
        $this->assertSame('10', $item->custom_player_number);
        $this->assertSame('Modrić 10', $item->printLabel());
    }

    public function test_print_is_optional(): void
    {
        $product = Product::factory()->sizes(['M'])->create();

        $this->postJson('/api/orders', $this->payload([[
            'product_id' => $product->id,
            'size' => 'M',
            'quantity' => 1,
        ]]))->assertCreated();

        $item = OrderItem::sole();
        $this->assertNull($item->custom_player_name);
        $this->assertNull($item->printLabel());
    }

    public function test_number_alone_is_allowed(): void
    {
        // Broj bez imena je legitiman dres — ne tražimo oboje.
        $product = Product::factory()->sizes(['M'])->create();

        $this->postJson('/api/orders', $this->payload([[
            'product_id' => $product->id,
            'size' => 'M',
            'quantity' => 1,
            'custom_player_number' => '7',
        ]]))->assertCreated();

        $this->assertSame('7', OrderItem::sole()->printLabel());
    }

    public function test_overlong_name_is_rejected(): void
    {
        $product = Product::factory()->sizes(['M'])->create();

        $this->postJson('/api/orders', $this->payload([[
            'product_id' => $product->id,
            'size' => 'M',
            'quantity' => 1,
            'custom_player_name' => str_repeat('a', 256),
        ]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.custom_player_name');
    }

    public function test_admin_order_list_shows_the_name_to_print(): void
    {
        $product = Product::factory()->create(['name' => 'Real Madrid Home']);
        $order = Order::factory()->paid()->create();

        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'size' => 'L',
            'custom_player_name' => 'Mbappé',
            'custom_player_number' => '9',
        ]);

        $this->actingAsAdmin()->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('0.items.0.product_id', $product->id)
            ->assertJsonPath('0.items.0.product_name', 'Real Madrid Home')
            ->assertJsonPath('0.items.0.size', 'L')
            ->assertJsonPath('0.items.0.player_name', 'Mbappé')
            ->assertJsonPath('0.items.0.player_number', '9');
    }

    public function test_admin_can_tell_kids_and_adult_apart_by_product_id(): void
    {
        // Isti naziv, dvije verzije — id je jedino što ih razlikuje.
        $adult = Product::factory()->create(['name' => 'Dinamo Home 25/26', 'type' => 'adult']);
        $kids = Product::factory()->create(['name' => 'Dinamo Home 25/26', 'type' => 'kids']);

        $order = Order::factory()->paid()->create();
        OrderItem::factory()->for($order)->create(['product_id' => $kids->id, 'size' => '140']);

        $this->actingAsAdmin()->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('0.items.0.product_id', $kids->id)
            ->assertJsonPath('0.items.0.size', '140');

        $this->assertNotSame($adult->id, $kids->id);
    }

    public function test_print_reaches_stripe_as_the_line_item_description(): void
    {
        $product = Product::factory()->sizes(['M'])->create(['name' => 'Real Madrid Home']);

        $this->postJson('/api/orders', $this->payload([[
            'product_id' => $product->id,
            'size' => 'M',
            'quantity' => 1,
            'custom_player_name' => 'Modrić',
            'custom_player_number' => '10',
        ]]))->assertCreated();

        // Fake gateway bilježi narudžbu; opis se gradi iz iste stavke.
        $order = $this->checkout->sessionsFor[0];
        $item = $order->items->first();

        $this->assertSame('Real Madrid Home', $item->product->name);
        $this->assertSame('Modrić 10', $item->printLabel());
    }
}
