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
            // Redoslijed je izvor istine: prva slika je primarna.
            'images' => $this->images ?? [],
        ]);
    }
}
