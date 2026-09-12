<?php

namespace App\Models;

use App\Enums\Audience;
use App\Enums\KitType;
use App\Enums\Personalization;
use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'club_or_team', 'category', 'kit_type', 'audience',
    'season', 'personalization', 'price', 'description', 'model_3d_url', 'status',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'kit_type' => KitType::class,
            'audience' => Audience::class,
            'personalization' => Personalization::class,
            'status' => ProductStatus::class,
            'price' => 'decimal:2',
        ];
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    /**
     * Gotova lista igrača/vozača — relevantna kad personalization dopušta preset.
     *
     * @return HasMany<ProductPlayer, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(ProductPlayer::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Primarna slika, uz fallback na prvu po sort_order.
     */
    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    /**
     * Postavlja punu listu vanjskih (URL) slika. Uploadane slike se ne diraju —
     * njima se upravlja preko POST /admin/products/{id}/images.
     *
     * URL-ovi koji pokazuju na vlastiti public disk se ignoriraju: `images[]` u
     * odgovoru vraća uploadane i vanjske slike izmiješano, pa klijent koji GET
     * odgovor vrati natrag u PUT ne smije time duplicirati uploadane slike.
     *
     * @param  array<int, string>  $urls
     */
    public function syncExternalImages(array $urls): void
    {
        $ownStoragePrefix = ProductImage::ownStoragePrefix();

        $urls = array_filter(
            $urls,
            fn (string $url) => ! str_starts_with($url, $ownStoragePrefix)
        );

        $this->images()->external()->delete();

        $sortOrder = (int) $this->images()->max('sort_order');

        foreach ($urls as $url) {
            $this->images()->create([
                'url' => $url,
                'sort_order' => ++$sortOrder,
            ]);
        }

        $this->ensurePrimaryImage();
        $this->unsetRelation('images');
    }

    /**
     * Jamči da proizvod sa slikama uvijek ima točno jednu primarnu.
     */
    public function ensurePrimaryImage(): void
    {
        if ($this->images()->where('is_primary', true)->exists()) {
            return;
        }

        $this->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
    }
}
