<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): static
    {
        return $this->actingAs(AdminUser::factory()->create(), 'sanctum');
    }

    /**
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dinamo Home 25/26',
            'club_or_team' => 'Dinamo Zagreb',
            'category' => 'football',
            'type' => 'adult',
            'season' => '2025/26',
            'price' => 89.99,
            'description' => 'Domaći dres.',
            'sizes' => ['S', 'M', 'L'],
        ], $overrides);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/products')->assertStatus(401);
    }

    public function test_index_returns_bare_array_including_drafts(): void
    {
        Product::factory()->create();
        Product::factory()->draft()->create();

        $response = $this->actingAsAdmin()->getJson('/api/admin/products');

        $response->assertOk()->assertJsonCount(2);
        $this->assertArrayNotHasKey('data', $response->json());
        $response->assertJsonStructure([['id', 'name', 'description', 'model_3d_url', 'images', 'sizes']]);
    }

    public function test_store_creates_product_as_draft_by_default(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload())
            ->assertCreated()
            ->assertJsonPath('name', 'Dinamo Home 25/26')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('price', 89.99)
            ->assertJsonPath('images', [])
            ->assertJsonPath('sizes', ['S', 'M', 'L']);

        $this->assertDatabaseCount('products', 1);
    }

    public function test_store_honours_explicit_status(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload(['status' => 'active']))
            ->assertCreated()
            ->assertJsonPath('status', 'active');
    }

    public function test_store_validates_required_fields_and_enums(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', ['category' => 'rugby'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'club_or_team', 'category', 'type', 'price', 'sizes']);
    }

    public function test_update_replaces_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload(['price' => 99.00]))
            ->assertOk()
            ->assertJsonPath('club_or_team', 'Dinamo Zagreb')
            ->assertJsonPath('price', 99);
    }

    public function test_update_clears_omitted_optional_fields_but_keeps_status(): void
    {
        $product = Product::factory()->create([
            'season' => '2024/25',
            'description' => 'Stari opis.',
            'model_3d_url' => 'https://cdn.example.com/old.glb',
            'status' => 'active',
        ]);

        $payload = $this->productPayload();
        unset($payload['season'], $payload['description']);

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $payload)
            ->assertOk()
            ->assertJsonPath('season', null)
            ->assertJsonPath('description', null)
            ->assertJsonPath('model_3d_url', null)
            // status se namjerno zadržava — izmjena ne smije tiho skinuti proizvod u draft
            ->assertJsonPath('status', 'active');
    }

    public function test_sizes_are_replaced_wholesale_on_update(): void
    {
        $product = Product::factory()->sizes(['S', 'M'])->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload([
                'sizes' => ['M', 'L', 'XL'],
            ]))
            ->assertOk()
            ->assertJsonPath('sizes', ['M', 'L', 'XL']);

        $this->assertSame(['M', 'L', 'XL'], $product->fresh()->sizes);
    }

    public function test_sizes_are_returned_in_canonical_order(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload([
                'sizes' => ['XL', 'S', 'L', 'M'],
            ]))
            ->assertOk()
            ->assertJsonPath('sizes', ['S', 'M', 'L', 'XL']);
    }

    public function test_at_least_one_size_is_required(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload(['sizes' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sizes');
    }

    public function test_duplicate_sizes_are_rejected(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload(['sizes' => ['M', 'M']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sizes.0');
    }

    public function test_removing_an_ordered_size_is_allowed(): void
    {
        // Veličina je snapshot na stavci narudžbe, pa je skidanje s ponude
        // više ne može osakatiti — za razliku od ranijeg FK-a na varijantu.
        $product = Product::factory()->sizes(['S', 'M'])->create();

        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'size' => 'S']);

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload(['sizes' => ['M']]))
            ->assertOk()
            ->assertJsonPath('sizes', ['M']);

        $this->assertSame('S', $order->fresh()->items->first()->size);
    }

    public function test_destroy_returns_204(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_destroy_is_refused_for_an_ordered_product(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'size' => 'XL']);

        // order_items.product_id je NOT NULL FK bez ON DELETE — brisanje bi
        // odnijelo i povijest narudžbe.
        $this->actingAsAdmin()
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertStatus(409);

        $this->assertDatabaseCount('products', 1);
        $this->assertSame($product->id, $order->fresh()->items->first()->product_id);
    }
}
