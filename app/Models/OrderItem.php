<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'product_variant_id', 'quantity', 'price_at_purchase',
    'product_player_id', 'custom_player_name', 'custom_player_number',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /** Tablica ima samo created_at. */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'price_at_purchase' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Igrač odabran s gotove liste; null kad je ime slobodno upisano ili
     * kad proizvod nema personalizaciju.
     *
     * @return BelongsTo<ProductPlayer, $this>
     */
    public function productPlayer(): BelongsTo
    {
        return $this->belongsTo(ProductPlayer::class);
    }
}
