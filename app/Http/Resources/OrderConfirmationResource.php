<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderConfirmationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Checkout URL ne živi na modelu — vrijedi samo za ovaj response, pa se
     * predaje kroz konstruktor. (->additional() ovdje ne ide: uz $wrap = null
     * Laravel bi zbog dodatnih podataka tijelo ipak zamotao u `data`.)
     */
    public function __construct(Order $resource, private string $checkoutUrl)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_reference' => $this->order_reference,
            'total_price' => (float) $this->total_price,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'stripe_checkout_url' => $this->checkoutUrl,
        ];
    }
}
