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
            'total_price' => (float) $this->total_price,
            'items' => $this->items->map(fn ($item) => [
                // order_items više ne drži snapshot — naziv i veličina se čitaju
                // kroz varijantu (FK je NOT NULL, pa varijanta uvijek postoji).
                'product_name' => $item->variant->product->name,
                'size' => $item->variant->size,
                'quantity' => $item->quantity,
                // Ime i broj za dobavljača, bez obzira dolaze li s gotove liste
                // ili iz slobodnog upisa kupca.
                'player_name' => $item->productPlayer?->player_name ?? $item->custom_player_name,
                'player_number' => $item->productPlayer?->player_number ?? $item->custom_player_number,
                'player_source' => match (true) {
                    $item->productPlayer !== null => 'preset',
                    filled($item->custom_player_name) => 'custom',
                    default => null,
                },
            ])->all(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
