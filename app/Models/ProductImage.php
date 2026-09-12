<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_id', 'url', 'sort_order', 'is_primary'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    /** Tablica ima samo created_at. */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Baza drži jedan `url` stupac, pa se uploadane slike razlikuju od vanjskih
     * po tome pokazuje li URL na vlastiti public disk.
     */
    public static function ownStoragePrefix(): string
    {
        return Storage::disk('public')->url('');
    }

    public function isExternal(): bool
    {
        return ! str_starts_with($this->url, static::ownStoragePrefix());
    }

    /** @param  Builder<ProductImage>  $query */
    public function scopeExternal($query): void
    {
        $query->where('url', 'not like', static::ownStoragePrefix().'%');
    }
}
