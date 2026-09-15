<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
                'data' => [['id', 'name', 'club_or_team', 'category', 'kit_type', 'audience', 'season', 'price', 'primary_image_url', 'status', 'available_sizes', 'created_at']],
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
        ProductImage::factory()->for($product)->create(['url' => Storage::disk('public')->url('products/a.jpg'), 'sort_order' => 2]);
        ProductImage::factory()->for($product)->create(['url' => Storage::disk('public')->url('products/b.jpg'), 'sort_order' => 1]);

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

    public function test_index_filters_by_size(): void
    {
        $withM = Product::factory()->create();
        ProductVariant::factory()->for($withM)->create(['size' => 'M']);
        ProductVariant::factory()->for($withM)->create(['size' => 'L']);

        $withoutM = Product::factory()->create();
        ProductVariant::factory()->for($withoutM)->create(['size' => 'XL']);

        $this->getJson('/api/products?size=M')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withM->id)
            ->assertJsonPath('meta.total_items', 1);

        // Bez filtera se vraćaju oba — filter stvarno sužava listu.
        $this->getJson('/api/products')->assertJsonPath('meta.total_items', 2);
    }

    public function test_size_filter_matches_by_existence_not_stock(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock_quantity' => 0]);

        $this->getJson('/api/products?size=M')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_unknown_size_returns_empty_list_not_validation_error(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create(['size' => 'M']);

        $this->getJson('/api/products?size=NEPOSTOJECA')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total_items', 0);
    }

    public function test_unknown_query_parameter_is_ignored(): void
    {
        Product::factory()->create();

        $this->getJson('/api/products?nepoznat=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_club_filter_matches_partially_and_ignores_case(): void
    {
        $product = Product::factory()->create(['club_or_team' => 'Real Madrid']);
        Product::factory()->create(['club_or_team' => 'FC Barcelona']);

        foreach (['Real', 'real', 'madrid'] as $term) {
            $this->getJson('/api/products?club='.$term)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $product->id);
        }
    }

    public function test_available_sizes_are_unique_and_canonically_sorted(): void
    {
        $product = Product::factory()->create();
        foreach (['XL', 'S', 'M'] as $size) {
            ProductVariant::factory()->for($product)->create(['size' => $size]);
        }

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.available_sizes', ['S', 'M', 'XL']);
    }

    public function test_available_sizes_sorts_numeric_kids_sizes_naturally(): void
    {
        $product = Product::factory()->create();
        foreach (['152', '128', '140'] as $size) {
            ProductVariant::factory()->for($product)->create(['size' => $size]);
        }

        $this->getJson('/api/products')
            ->assertJsonPath('data.0.available_sizes', ['128', '140', '152']);
    }

    public function test_size_filter_does_not_truncate_available_sizes(): void
    {
        $product = Product::factory()->create();
        foreach (['S', 'M', 'L'] as $size) {
            ProductVariant::factory()->for($product)->create(['size' => $size]);
        }

        $this->getJson('/api/products?size=M')
            ->assertOk()
            ->assertJsonPath('data.0.available_sizes', ['S', 'M', 'L']);
    }

    public function test_index_can_sort_by_column_and_direction(): void
    {
        $cheap = Product::factory()->create(['price' => 10]);
        Product::factory()->create(['price' => 50]);
        $expensive = Product::factory()->create(['price' => 90]);

        $this->getJson('/api/products?sort=price&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $cheap->id)
            ->assertJsonPath('data.2.id', $expensive->id);

        $this->getJson('/api/products?sort=price&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $expensive->id)
            ->assertJsonPath('data.2.id', $cheap->id);
    }

    public function test_index_rejects_unknown_sort_column(): void
    {
        $this->getJson('/api/products?sort=password')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_index_defaults_to_newest_first(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_index_accepts_per_page(): void
    {
        Product::factory()->count(5)->create();

        $this->getJson('/api/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total_pages', 3)
            ->assertJsonPath('meta.total_items', 5);
    }

    public function test_index_rejects_per_page_out_of_range(): void
    {
        $this->getJson('/api/products?per_page=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_filters_endpoint_returns_distinct_clubs_and_seasons(): void
    {
        Product::factory()->create(['club_or_team' => 'Real Madrid', 'season' => '24/25']);
        Product::factory()->create(['club_or_team' => 'Real Madrid', 'season' => '25/26']);
        Product::factory()->create(['club_or_team' => 'GNK Dinamo', 'season' => '25/26']);
        // Bez sezone i draft proizvod — ni jedno se ne smije pojaviti u popisima.
        Product::factory()->create(['club_or_team' => 'KK Cibona', 'season' => null]);
        Product::factory()->draft()->create(['club_or_team' => 'Skrivena Momčad', 'season' => '99/00']);

        $response = $this->getJson('/api/products/filters')->assertOk();

        $response->assertJsonPath('clubs', ['GNK Dinamo', 'KK Cibona', 'Real Madrid'])
            ->assertJsonPath('seasons', ['25/26', '24/25']);
    }

    public function test_filters_route_is_not_shadowed_by_product_detail(): void
    {
        $this->getJson('/api/products/filters')
            ->assertOk()
            ->assertJsonStructure(['clubs', 'seasons']);
    }
}
