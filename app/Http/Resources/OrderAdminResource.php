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
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ],
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'total_price' => (float) $this->total_price,
            'items' => $this->items->map(fn ($item) => [
                // Id je jedino pouzdano razlikovanje: "Dinamo Home 25/26" postoji
                // i kao dječji i kao dres za odrasle, pod istim nazivom.
                'product_id' => $item->product_id,
                // Naziv se čita kroz proizvod (FK je NOT NULL), a veličina je
                // snapshot na stavci — proizvod je kasnije smije prestati nuditi.
                'product_name' => $item->product->name,
                'size' => $item->size,
                'quantity' => $item->quantity,
                // Ime i broj za tisak, onako kako ih je kupac upisao.
                'player_name' => $item->custom_player_name,
                'player_number' => $item->custom_player_number,
            ])->all(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
