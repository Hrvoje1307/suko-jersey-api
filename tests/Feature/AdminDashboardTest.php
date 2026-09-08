<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'sanctum');
    }

    public function test_requires_admin(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_returns_monthly_totals_and_all_time_top_products(): void
    {
        $thisMonth = Order::factory()->create([
            'total_price' => 100.00,
            'created_at' => now()->startOfMonth()->addDay(),
        ]);
        Order::factory()->create([
            'total_price' => 50.00,
            'created_at' => now()->startOfMonth()->addDays(2),
        ]);

        $lastMonth = Order::factory()->create([
            'total_price' => 999.00,
            'created_at' => now()->startOfMonth()->subDays(3),
        ]);

        OrderItem::factory()->for($thisMonth)->create(['product_name' => 'Dres A', 'quantity' => 3]);
        OrderItem::factory()->for($lastMonth)->create(['product_name' => 'Dres A', 'quantity' => 4]);
        OrderItem::factory()->for($thisMonth)->create(['product_name' => 'Dres B', 'quantity' => 2]);

        $response = $this->actingAsAdmin()->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('total_orders_this_month', 2)
            ->assertJsonPath('revenue_this_month', 150)
            ->assertJsonPath('top_products.0.product_name', 'Dres A')
            ->assertJsonPath('top_products.0.units_sold', 7)
            ->assertJsonPath('top_products.1.product_name', 'Dres B')
            ->assertJsonPath('top_products.1.units_sold', 2);
    }

    public function test_returns_zeroes_when_empty(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('total_orders_this_month', 0)
            ->assertJsonPath('revenue_this_month', 0)
            ->assertJsonPath('top_products', []);
    }
}
