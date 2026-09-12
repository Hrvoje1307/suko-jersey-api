<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'url' => Storage::disk('public')->url('products/'.fake()->uuid().'.jpg'),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function external(?string $url = null): static
    {
        return $this->state(fn () => [
            'url' => $url ?? fake()->imageUrl(),
        ]);
    }
}
