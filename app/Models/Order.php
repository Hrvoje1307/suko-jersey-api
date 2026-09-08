<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'order_reference', 'customer_name', 'customer_email', 'customer_phone',
    'shipping_line1', 'shipping_line2', 'shipping_city', 'shipping_postal_code',
    'shipping_country', 'status', 'total_price', 'tracking_number_internal',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_price' => 'decimal:2',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Fiksni offset od datuma narudžbe (config/shop.php).
     */
    public function estimatedDelivery(): Carbon
    {
        return $this->created_at->copy()->addDays(config('shop.estimated_delivery_days'));
    }
}
