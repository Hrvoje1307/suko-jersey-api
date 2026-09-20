<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'product_id', 'size', 'quantity', 'price_at_purchase',
    'custom_player_name', 'custom_player_number',
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Ime i broj za tisak, onako kako ih je kupac upisao. Prazno kad dres ide
     * bez tiska.
     */
    public function printLabel(): ?string
    {
        $label = trim(implode(' ', array_filter([
            $this->custom_player_name,
            $this->custom_player_number,
        ])));

        return $label === '' ? null : $label;
    }
}
