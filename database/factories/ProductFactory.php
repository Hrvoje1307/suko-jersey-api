<?php

namespace Database\Factories;

use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Jersey',
            'club_or_team' => fake()->company(),
            'category' => fake()->randomElement(ProductCategory::cases()),
            'type' => ProductType::Adult,
            'season' => '2025/26',
            'price' => fake()->randomFloat(2, 40, 150),
            'description' => fake()->sentence(),
            'sizes' => ['S', 'M', 'L', 'XL'],
            'images' => [],
            'model_3d_url' => null,
            'status' => ProductStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Draft]);
    }

    public function soldOut(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::SoldOut]);
    }

    /**
     * @param  array<int, string>  $sizes
     */
    public function sizes(array $sizes): static
    {
        return $this->state(fn () => ['sizes' => $sizes]);
    }

    /**
     * @param  array<int, string>  $urls
     */
    public function images(array $urls): static
    {
        return $this->state(fn () => ['images' => $urls]);
    }
}
