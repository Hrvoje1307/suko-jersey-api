<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'sanctum');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/orders')->assertStatus(401);
    }

    public function test_rejects_non_admin_token(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertStatus(403);
    }

    public function test_index_returns_bare_array_in_spec_shape(): void
    {
        $order = Order::factory()->create(['total_price' => 120.00]);
        OrderItem::factory()->for($order)->create(['product_name' => 'Dres', 'size' => 'L', 'quantity' => 2]);

        $response = $this->actingAsAdmin()->getJson('/api/admin/orders');

        $response->assertOk()->assertJsonCount(1);
        $this->assertArrayNotHasKey('data', $response->json());

        $response->assertJsonStructure([[
            'id', 'order_reference', 'customer' => ['name', 'email'],
            'status', 'total_price', 'items' => [['product_name', 'size', 'quantity']], 'created_at',
        ]])
            ->assertJsonPath('0.total_price', 120)
            ->assertJsonPath('0.items.0.product_name', 'Dres');
    }

    public function test_index_filters_by_status(): void
    {
        Order::factory()->create(['status' => OrderStatus::Ordered]);
        $shipped = Order::factory()->create(['status' => OrderStatus::Shipped]);

        $this->actingAsAdmin()
            ->getJson('/api/admin/orders?status=shipped')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $shipped->id);
    }

    public function test_index_rejects_invalid_status_filter(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/admin/orders?status=nepostojeci')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_status_update_sets_tracking_and_notifies_customer(): void
    {
        Notification::fake();

        $order = Order::factory()->create(['status' => OrderStatus::ArrivedHr]);

        $this->actingAsAdmin()
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'shipped',
                'tracking_number_internal' => 'HR999',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'shipped');

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('HR999', $order->tracking_number_internal);

        Notification::assertSentOnDemand(OrderStatusChanged::class);
    }

    public function test_status_can_move_backwards(): void
    {
        Notification::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

        $this->actingAsAdmin()
            ->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'ordered'])
            ->assertOk()
            ->assertJsonPath('status', 'ordered');
    }

    public function test_no_notification_when_status_is_unchanged(): void
    {
        Notification::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Ordered]);

        $this->actingAsAdmin()
            ->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'ordered'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_status_update_validates_enum(): void
    {
        $order = Order::factory()->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'izgubljeno'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
