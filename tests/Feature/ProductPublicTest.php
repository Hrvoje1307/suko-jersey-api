<?php

namespace Tests\Feature;

use App\Models\Product;
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
            'type' => 'adult',
            'season' => '2025/26',
        ]);

        Product::factory()->create([
            'category' => 'formula',
            'club_or_team' => 'Ferrari',
            'type' => 'kids',
            'season' => '2024/25',
        ]);

        $query = 'category=football&club=Dinamo&type=adult&season=2025/26';

        $this->getJson('/api/products?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_meta_matches_spec_shape(): void
    {
        Product::factory()->count(3)->sizes(['M'])->create();

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'club_or_team', 'category', 'type', 'season', 'price', 'primary_image_url', 'status', 'sizes', 'created_at']],
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
        // Prva u nizu je primarna — redoslijed je jedini izvor istine.
        Product::factory()->images([
            'https://cdn.example.com/b.jpg',
            'https://cdn.example.com/a.jpg',
        ])->create();

        $url = $this->getJson('/api/products')->json('data.0.primary_image_url');

        $this->assertSame('https://cdn.example.com/b.jpg', $url);
    }

    public function test_show_returns_detail_with_images_and_sizes(): void
    {
        $product = Product::factory()
            ->sizes(['L'])
            ->images(['https://cdn.example.com/front.jpg'])
            ->create(['model_3d_url' => null]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'description', 'model_3d_url', 'images', 'sizes'])
            ->assertJsonPath('model_3d_url', null)
            ->assertJsonPath('images', ['https://cdn.example.com/front.jpg'])
            ->assertJsonPath('sizes', ['L']);
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
        $withM = Product::factory()->sizes(['M', 'L'])->create();
        Product::factory()->sizes(['XL'])->create();

        $this->getJson('/api/products?size=M')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withM->id)
            ->assertJsonPath('meta.total_items', 1);

        // Bez filtera se vraćaju oba — filter stvarno sužava listu.
        $this->getJson('/api/products')->assertJsonPath('meta.total_items', 2);
    }

    public function test_unknown_size_returns_empty_list_not_validation_error(): void
    {
        Product::factory()->sizes(['M'])->create();

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

    public function test_summary_exposes_type_and_sizes(): void
    {
        Product::factory()->sizes(['M'])->create(['type' => 'kids']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'kids')
            ->assertJsonPath('data.0.sizes', ['M']);
    }

    public function test_sizes_are_canonically_sorted(): void
    {
        Product::factory()->sizes(['XL', 'S', 'M'])->create();

        $sizes = $this->getJson('/api/products')->json('data.0.sizes');

        $this->assertSame(['S', 'M', 'XL'], $sizes);
    }

    public function test_sizes_sort_numeric_kids_sizes_naturally(): void
    {
        Product::factory()->sizes(['152', '128', '140'])->create();

        $sizes = $this->getJson('/api/products')->json('data.0.sizes');

        $this->assertSame(['128', '140', '152'], $sizes);
    }

    public function test_size_filter_does_not_truncate_the_size_list(): void
    {
        // Kartica mora ponuditi sve veličine proizvoda, ne samo filtriranu.
        Product::factory()->sizes(['S', 'M', 'L'])->create();

        $sizes = $this->getJson('/api/products?size=M')->json('data.0.sizes');

        $this->assertSame(['S', 'M', 'L'], $sizes);
    }

    public function test_index_does_not_run_a_query_per_product(): void
    {
        Product::factory()->count(5)->sizes(['M', 'L'])->create();

        \DB::enableQueryLog();
        $this->getJson('/api/products')->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Slike i veličine su stupci na proizvodu, pa nema eager loada uopće:
        // ostaju samo count i dohvat stranice.
        $this->assertLessThanOrEqual(2, $queries);
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
