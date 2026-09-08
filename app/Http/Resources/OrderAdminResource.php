<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_reference' => $this->order_reference,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
            ],
            'status' => $this->status->value,
            'total_price' => (float) $this->total_price,
            'items' => $this->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'size' => $item->size,
                'quantity' => $item->quantity,
            ])->all(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
