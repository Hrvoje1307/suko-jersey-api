<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_active_products_by_default(): void
    {
        $active = Product::factory()->create();
        Product::factory()->draft()->create();
        Product::factory()->soldOut()->create();

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_index_can_explicitly_request_sold_out(): void
    {
        Product::factory()->create();
        $soldOut = Product::factory()->soldOut()->create();

        $this->getJson('/api/products?status=sold_out')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $soldOut->id);
    }

    public function test_index_rejects_draft_status_filter(): void
    {
        $this->getJson('/api/products?status=draft')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_index_filters(): void
    {
        $match = Product::factory()->create([
            'category' => 'football',
            'club_or_team' => 'Dinamo Zagreb',
            'kit_type' => 'home',
            'audience' => 'men',
            'season' => '2025/26',
        ]);

        Product::factory()->create([
            'category' => 'f1',
            'club_or_team' => 'Ferrari',
            'kit_type' => 'away',
            'audience' => 'kids',
            'season' => '2024/25',
        ]);

        $query = 'category=football&club=Dinamo&kit_type=home&audience=men&season=2025/26';

        $this->getJson('/api/products?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_meta_matches_spec_shape(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'club_or_team', 'category', 'kit_type', 'audience', 'season', 'price', 'primary_image_url', 'status']],
                'meta' => ['current_page', 'total_pages', 'total_items'],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total_pages', 1)
            ->assertJsonPath('meta.total_items', 3)
            ->assertJsonMissingPath('links');
    }

    public function test_price_is_serialized_as_number(): void
    {
        Product::factory()->create(['price' => 89.99]);

        $data = $this->getJson('/api/products')->json('data.0.price');

        $this->assertIsFloat($data);
        $this->assertSame(89.99, $data);
    }

    public function test_primary_image_url_falls_back_to_first_image(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->create(['path' => 'products/a.jpg', 'sort_order' => 2]);
        ProductImage::factory()->for($product)->create(['path' => 'products/b.jpg', 'sort_order' => 1]);

        $url = $this->getJson('/api/products')->json('data.0.primary_image_url');

        $this->assertStringContainsString('products/b.jpg', $url);
    }

    public function test_show_returns_detail_with_images_and_variants(): void
    {
        $product = Product::factory()->create(['model_3d_url' => null]);
        ProductImage::factory()->for($product)->primary()->create();
        ProductVariant::factory()->for($product)->create(['size' => 'L', 'stock_quantity' => 4]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonStructure([
                'id', 'name', 'description', 'model_3d_url',
                'images' => [['url', 'sort_order', 'is_primary']],
                'variants' => [['id', 'size', 'stock_quantity']],
            ])
            ->assertJsonPath('model_3d_url', null)
            ->assertJsonPath('variants.0.size', 'L')
            ->assertJsonPath('variants.0.stock_quantity', 4);
    }

    public function test_show_returns_404_for_draft_product(): void
    {
        $product = Product::factory()->draft()->create();

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_show_returns_404_for_missing_product(): void
    {
        $this->getJson('/api/products/999')->assertNotFound();
    }
}
