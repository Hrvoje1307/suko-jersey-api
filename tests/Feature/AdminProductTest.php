<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'kit_type' => 'home',
            'audience' => 'men',
            'season' => '2025/26',
            'price' => 89.99,
            'description' => 'Domaći dres.',
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
        $response->assertJsonStructure([['id', 'name', 'description', 'model_3d_url', 'images', 'variants']]);
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
            ->assertJsonPath('variants', []);

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
            ->assertJsonValidationErrors(['name', 'club_or_team', 'category', 'kit_type', 'audience', 'price']);
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

    public function test_variants_update_refuses_to_remove_an_ordered_size(): void
    {
        $product = Product::factory()->create();
        $small = ProductVariant::factory()->for($product)->create(['size' => 'S', 'stock_quantity' => 3]);
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock_quantity' => 3]);

        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['product_variant_id' => $small->id]);

        // order_items.product_variant_id je NOT NULL FK bez ON DELETE, pa se
        // naručena veličina ne može ukloniti bez gubitka povijesti narudžbe.
        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}/variants", [
                'variants' => [['size' => 'M', 'stock_quantity' => 3]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants');

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame($small->id, $order->fresh()->items->first()->product_variant_id);
    }

    public function test_destroy_returns_204_and_cascades_to_variants_and_images(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['size' => 'XL']);
        ProductImage::factory()->for($product)->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_destroy_is_refused_for_an_ordered_product(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['size' => 'XL']);

        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['product_variant_id' => $variant->id]);

        // Bez snapshota u order_items brisanje bi odnijelo i povijest narudžbe.
        $this->actingAsAdmin()
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertStatus(409);

        $this->assertDatabaseCount('products', 1);
        $this->assertSame($variant->id, $order->fresh()->items->first()->product_variant_id);
    }

    public function test_variants_update_replaces_missing_sizes(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['size' => 'S', 'stock_quantity' => 1]);
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock_quantity' => 2]);

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}/variants", [
                'variants' => [
                    ['size' => 'M', 'stock_quantity' => 10],
                    ['size' => 'L', 'stock_quantity' => 7],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'variants');

        $sizes = $product->fresh()->variants->pluck('stock_quantity', 'size')->all();
        $this->assertSame(['M' => 10, 'L' => 7], $sizes);
    }

    public function test_variants_update_validates_payload(): void
    {
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}/variants", [
                'variants' => [['size' => 'M', 'stock_quantity' => -1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants.0.stock_quantity');
    }

    public function test_image_upload_stores_files_and_marks_first_as_primary(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post("/api/admin/products/{$product->id}/images", [
                'images' => [
                    UploadedFile::fake()->image('front.jpg'),
                    UploadedFile::fake()->image('back.jpg'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(2, 'images')
            ->assertJsonPath('images.0.is_primary', true)
            ->assertJsonPath('images.1.is_primary', false);

        $this->assertCount(2, Storage::disk('public')->files("products/{$product->id}"));
    }

    public function test_image_upload_rejects_non_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $this->actingAsAdmin()
            ->post("/api/admin/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->create('malware.pdf', 10, 'application/pdf')],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('images.0');
    }
}
