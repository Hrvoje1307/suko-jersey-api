<?php

namespace App\Models;

use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'club_or_team', 'category', 'type', 'season',
    'price', 'description', 'sizes', 'images', 'model_3d_url', 'status',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** Kanonski redoslijed slovnih veličina; brojčane (dječje) idu nakon njih. */
    private const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'price' => 'decimal:2',
            // jsonb u Postgresu, JSON tekst u sqliteu — isti cast radi na oba.
            'sizes' => 'array',
            'images' => 'array',
        ];
    }

    /**
     * Stavke narudžbi koje pokazuju na ovaj proizvod. Veličina se ne čuva ovdje
     * nego na stavci, kao snapshot u trenutku kupnje.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Veličine poredane za birač na frontendu: slovne kanonski (S prije M
     * prije L), brojčane dječje uzlazno iza njih.
     *
     * @return array<int, string>
     */
    public function sortedSizes(): array
    {
        $sizes = $this->sizes ?? [];

        usort($sizes, fn (string $a, string $b) => self::sizeSortKey($a) <=> self::sizeSortKey($b));

        return array_values($sizes);
    }

    /**
     * Nudi li proizvod traženu veličinu. Usporedba je neosjetljiva na
     * velika/mala slova jer veličine dolaze iz slobodnog upisa u adminu.
     */
    public function hasSize(string $size): bool
    {
        return in_array(
            strtoupper($size),
            array_map(strtoupper(...), $this->sizes ?? []),
            true,
        );
    }

    /**
     * Prva slika je ujedno primarna — redoslijed u nizu je jedini izvor istine.
     */
    public function primaryImageUrl(): ?string
    {
        return ($this->images ?? [])[0] ?? null;
    }

    /**
     * Ključ za sortiranje veličina: slovne kanonski, brojčane dječje uzlazno
     * iza njih, sve ostalo abecedno na kraju. Javan jer isti poredak treba i
     * lista veličina na /products/filters.
     */
    public static function sizeSortKey(string $size): string
    {
        $index = array_search(strtoupper($size), self::SIZE_ORDER, true);

        if ($index !== false) {
            return sprintf('0-%02d', $index);
        }

        return is_numeric($size)
            ? sprintf('1-%010.2f', (float) $size)
            : '2-'.strtoupper($size);
    }
}
