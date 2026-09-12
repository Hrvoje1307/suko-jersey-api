<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
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
            'price' => 89.99,
            'status' => 'active',
        ], $overrides);
    }

    public function test_store_accepts_image_urls_and_a_single_3d_model(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload([
                'model_3d_url' => 'https://cdn.example.com/dinamo.glb',
                'image_urls' => [
                    'https://cdn.example.com/front.jpg',
                    'https://cdn.example.com/back.jpg',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('model_3d_url', 'https://cdn.example.com/dinamo.glb')
            ->assertJsonCount(2, 'images')
            ->assertJsonPath('images.0.url', 'https://cdn.example.com/front.jpg')
            ->assertJsonPath('images.0.sort_order', 1)
            ->assertJsonPath('images.0.is_primary', true)
            ->assertJsonPath('images.1.url', 'https://cdn.example.com/back.jpg')
            ->assertJsonPath('images.1.is_primary', false);
    }

    public function test_image_urls_appear_in_public_catalogue(): void
    {
        $this->actingAsAdmin()->postJson('/api/admin/products', $this->productPayload([
            'image_urls' => ['https://cdn.example.com/front.jpg'],
        ]))->assertCreated();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.primary_image_url', 'https://cdn.example.com/front.jpg');
    }

    public function test_update_replaces_the_full_image_url_list(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->external('https://cdn.example.com/old.jpg')->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload([
                'image_urls' => ['https://cdn.example.com/new.jpg'],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'images')
            ->assertJsonPath('images.0.url', 'https://cdn.example.com/new.jpg');
    }

    public function test_update_without_image_urls_clears_them(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->external()->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload())
            ->assertOk()
            ->assertJsonPath('images', []);
    }

    public function test_image_urls_do_not_touch_uploaded_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $this->actingAsAdmin()->post("/api/admin/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('front.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $response = $this->actingAsAdmin()->putJson("/api/admin/products/{$product->id}", $this->productPayload([
            'image_urls' => ['https://cdn.example.com/back.jpg'],
        ]));

        // Uploadana slika ostaje i zadržava primary; vanjska se dodaje uz nju.
        $response->assertOk()->assertJsonCount(2, 'images');
        $this->assertStringContainsString('/storage/products/', $response->json('images.0.url'));
        $this->assertTrue($response->json('images.0.is_primary'));
        $this->assertSame('https://cdn.example.com/back.jpg', $response->json('images.1.url'));
        $this->assertFalse($response->json('images.1.is_primary'));
    }

    /**
     * Bez Storage::fake() — fake disk vraća relativan URL, a provjera ovisi
     * o apsolutnom URL-u kakav pravi public disk stvarno generira.
     */
    public function test_get_put_round_trip_does_not_duplicate_uploaded_images(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->primary()->create(['url' => Storage::disk('public')->url('products/1/front.png')]);
        ProductImage::factory()->for($product)->external('https://cdn.example.com/back.jpg')->create(['sort_order' => 2]);

        // Klijent dohvati proizvod i vrati cijeli images[] natrag u image_urls.
        $echoed = array_column($this->getJson("/api/products/{$product->id}")->json('images'), 'url');
        $this->assertCount(2, $echoed);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload([
                'image_urls' => $echoed,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'images');

        $this->assertSame($echoed, array_column($response->json('images'), 'url'));
    }

    public function test_primary_is_reassigned_when_the_primary_url_is_removed(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->external('https://cdn.example.com/a.jpg')->primary()->create();

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}", $this->productPayload([
                'image_urls' => ['https://cdn.example.com/b.jpg', 'https://cdn.example.com/c.jpg'],
            ]))
            ->assertOk()
            ->assertJsonPath('images.0.url', 'https://cdn.example.com/b.jpg')
            ->assertJsonPath('images.0.is_primary', true)
            ->assertJsonPath('images.1.is_primary', false);
    }

    public function test_image_urls_are_validated(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload([
                'image_urls' => ['nije-url', 'https://cdn.example.com/a.jpg', 'https://cdn.example.com/a.jpg'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image_urls.0', 'image_urls.1', 'image_urls.2']);
    }

    public function test_model_3d_url_must_be_a_single_url(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', $this->productPayload([
                'model_3d_url' => ['https://cdn.example.com/a.glb', 'https://cdn.example.com/b.glb'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('model_3d_url');
    }
}
