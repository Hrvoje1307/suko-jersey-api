<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Javni status po samoj referenci. Namjerno minimalan — referenca je kratka i
 * pogodiva, pa se osobni podaci i tracking otkrivaju samo na /orders/lookup,
 * koji uz referencu traži i email.
 *
 * @mixin Order
 */
class OrderPaymentStatusResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_reference' => $this->order_reference,
            'payment_status' => $this->payment_status->value,
            'status_label' => $this->isPaid()
                ? $this->status->label()
                : $this->payment_status->label(),
        ];
    }
}
