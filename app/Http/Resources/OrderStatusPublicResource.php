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
            'status_label' => $this->status->label(),
            'estimated_delivery' => $this->estimatedDelivery()->toDateString(),
            // Tracking broj je interni — kupcu se otkriva tek kad je pošiljka poslana.
            'tracking_number' => $this->status->exposesTracking()
                ? $this->tracking_number_internal
                : null,
        ];
    }
}
