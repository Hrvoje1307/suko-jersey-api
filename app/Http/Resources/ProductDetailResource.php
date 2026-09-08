<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * @mixin Product
 */
class ProductDetailResource extends ProductSummaryResource
{
    // Spec vraća ProductDetail kao goli objekt, bez `data` omotača.
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'model_3d_url' => $this->model_3d_url,
            'images' => $this->images->map(fn ($image) => [
                'url' => $image->url(),
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
            ])->all(),
            'variants' => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'size' => $variant->size,
                'stock_quantity' => $variant->stock_quantity,
            ])->all(),
        ]);
    }
}
