<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'order_reference', 'customer_id',
    'shipping_address_line1', 'shipping_address_line2', 'shipping_city',
    'shipping_postal_code', 'shipping_country',
    'status', 'total_price', 'tracking_number_internal',
    'payment_status', 'stripe_checkout_session_id', 'stripe_payment_intent_id',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'total_price' => 'decimal:2',
        ];
    }

    /**
     * Plaćanje je zasebna os od `status` — dok ovo nije true, narudžba je
     * samo zapisana namjera, ne i stvarna narudžba.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
