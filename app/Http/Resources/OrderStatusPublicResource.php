<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderStatusPublicResource extends JsonResource
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
            // Enum za tracker na frontendu. Nije osoban podatak, a tracking
            // broj ostaje skriven do slanja (niže).
            'status' => $this->status->value,
            // Dok plaćanje nije prošlo, fulfillment status ("Narudžba
            // zaprimljena") bi kupca krivo uvjerio da je sve gotovo.
            'status_label' => $this->isPaid()
                ? $this->status->label()
                : $this->payment_status->label(),
            'estimated_delivery' => $this->estimatedDelivery()->toDateString(),
            // Tracking broj je interni — kupcu se otkriva tek kad je pošiljka poslana.
            'tracking_number' => $this->status->exposesTracking()
                ? $this->tracking_number_internal
                : null,
        ];
    }
}
