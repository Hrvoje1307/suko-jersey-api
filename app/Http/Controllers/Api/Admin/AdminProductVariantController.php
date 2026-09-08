<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVariantsRequest;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class AdminProductVariantController extends Controller
{
    /**
     * Replace semantika: varijante koje nisu poslane se brišu.
     */
    public function update(UpdateVariantsRequest $request, Product $product): ProductDetailResource
    {
        $variants = $request->validated('variants');

        DB::transaction(function () use ($product, $variants) {
            $sizes = array_column($variants, 'size');

            $product->variants()->whereNotIn('size', $sizes)->delete();

            foreach ($variants as $variant) {
                $product->variants()->updateOrCreate(
                    ['size' => $variant['size']],
                    ['stock_quantity' => $variant['stock_quantity']],
                );
            }
        });

        return new ProductDetailResource($product->load(['images', 'variants']));
    }
}
