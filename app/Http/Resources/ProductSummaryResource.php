<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'club_or_team' => $this->club_or_team,
            'category' => $this->category->value,
            'kit_type' => $this->kit_type->value,
            'audience' => $this->audience->value,
            'season' => $this->season,
            // Spec traži `number` — decimal cast bi ovdje vratio string.
            'price' => (float) $this->price,
            'primary_image_url' => $this->primaryImage()?->url(),
            'status' => $this->status->value,
        ];
    }
}
