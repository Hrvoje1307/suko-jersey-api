<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slike su običan niz URL-ova na proizvodu; redoslijed je jedini izvor istine
 * o tome koja je primarna. Upload datoteka ne postoji — slike se hostaju
 * vanjski i u admin se šalju kao URL-ovi.
 */
class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): static
    {
        return $this->actingAs(AdminUser::factory()->create(), 'sanctum');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dinamo Home 25/26',
            'club_or_team' => 'Dinamo Zagreb',
            'category' => 'football',
            'type' => 'adult',
            'price' => 89.99,
            'sizes' => ['M', 'L'],
            'status' => 'active',
        ], $overrides);
    }

    public function test_store_accepts_images_and_a_single_3d_model(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload([
                'model_3d_url' => 'https://cdn.example.com/dinamo.glb',
                'images' => [
                    'https://cdn.example.com/front.jpg',
                    'https://cdn.example.com/back.jpg',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('model_3d_url', 'https://cdn.example.com/dinamo.glb')
            ->assertJsonCount(2, 'images')
            ->assertJsonPath('images.0', 'https://cdn.example.com/front.jpg')
            ->assertJsonPath('images.1', 'https://cdn.example.com/back.jpg');
    }

    public function test_first_image_is_the_primary_one_in_the_catalogue(): void
    {
        $product = Product::factory()->images([
            'https://cdn.example.com/front.jpg',
            'https://cdn.example.com/back.jpg',
        ])->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.primary_image_url', 'https://cdn.example.com/front.jpg');

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonCount(2, 'images');
    }

    public function test_product_without_images_reports_null_primary(): void
    {
        Product::factory()->images([])->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.primary_image_url', null);
    }

    public function test_update_replaces_the_full_image_list(): void
    {
        $product = Product::factory()->images(['https://cdn.example.com/old.jpg'])->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload([
                'images' => ['https://cdn.example.com/new.jpg'],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'images')
            ->assertJsonPath('images.0', 'https://cdn.example.com/new.jpg');
    }

    public function test_update_without_images_clears_them(): void
    {
        // PUT je full replace, pa izostavljanje briše, a ne zadržava.
        $product = Product::factory()->images(['https://cdn.example.com/old.jpg'])->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload())
            ->assertOk()
            ->assertJsonCount(0, 'images');
    }

    public function test_images_are_validated(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload([
                'images' => ['nije-url', 'https://cdn.example.com/ok.jpg'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('images.0');
    }

    public function test_duplicate_images_are_rejected(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload([
                'images' => [
                    'https://cdn.example.com/front.jpg',
                    'https://cdn.example.com/front.jpg',
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('images.0');
    }

    public function test_model_3d_url_must_be_a_single_url(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload([
                'model_3d_url' => ['https://a.example.com/x.glb', 'https://b.example.com/y.glb'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('model_3d_url');
    }
}
