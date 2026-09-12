<?php

namespace App\Models;

use Database\Factories\ProductPlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'player_name', 'player_number', 'sort_order'])]
class ProductPlayer extends Model
{
    /** @use HasFactory<ProductPlayerFactory> */
    use HasFactory;

    /** Tablica ima samo created_at (vidi schema.sql). */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
