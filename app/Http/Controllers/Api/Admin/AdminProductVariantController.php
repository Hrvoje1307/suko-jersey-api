<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVariantsRequest;
use App\Http\Resources\ProductDetailResource;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProductVariantController extends Controller
{
    /**
     * Replace semantika: varijante koje nisu poslane se brišu.
     *
     * order_items.product_variant_id je NOT NULL FK bez ON DELETE, pa se veličina
     * koja je već naručena ne može ukloniti — takav zahtjev se odbija s 422
     * umjesto da padne na constraintu i sruši povijest narudžbi.
     */
    public function update(UpdateVariantsRequest $request, Product $product): ProductDetailResource
    {
        $variants = $request->validated('variants');

        DB::transaction(function () use ($product, $variants) {
            $sizes = array_column($variants, 'size');

            $removed = $product->variants()->whereNotIn('size', $sizes)->get();

            if ($removed->isNotEmpty()) {
                $ordered = OrderItem::query()
                    ->whereIn('product_variant_id', $removed->pluck('id'))
                    ->pluck('product_variant_id')
                    ->unique();

                if ($ordered->isNotEmpty()) {
                    $names = $removed->whereIn('id', $ordered)->pluck('size')->implode(', ');

                    throw ValidationException::withMessages([
                        'variants' => ["Veličine koje se već nalaze u narudžbama se ne mogu ukloniti: {$names}."],
                    ]);
                }

                $product->variants()->whereIn('id', $removed->pluck('id'))->delete();
            }

            foreach ($variants as $variant) {
                $product->variants()->updateOrCreate(
                    ['size' => $variant['size']],
                    ['stock_quantity' => $variant['stock_quantity']],
                );
            }
        });

        return new ProductDetailResource($product->load(['images', 'variants', 'players']));
    }
}
