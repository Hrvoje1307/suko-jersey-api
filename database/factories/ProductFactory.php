<?php

namespace Database\Factories;

use App\Enums\Audience;
use App\Enums\KitType;
use App\Enums\Personalization;
use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
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
            'kit_type' => fake()->randomElement(KitType::cases()),
            'audience' => fake()->randomElement(Audience::cases()),
            'season' => '2025/26',
            'personalization' => Personalization::None,
            'price' => fake()->randomFloat(2, 40, 150),
            'description' => fake()->sentence(),
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
}
