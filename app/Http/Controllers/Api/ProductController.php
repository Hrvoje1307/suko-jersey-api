<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request): ProductCollection
    {
        $products = Product::query()
            ->with(['images', 'variants'])
            ->when($request->input('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->input('club'), fn ($q, $v) => $q->where('club_or_team', 'like', "%{$v}%"))
            ->when($request->input('kit_type'), fn ($q, $v) => $q->where('kit_type', $v))
            ->when($request->input('audience'), fn ($q, $v) => $q->where('audience', $v))
            ->when($request->input('season'), fn ($q, $v) => $q->where('season', $v))
            // Bez eksplicitnog filtera javno se vraćaju samo aktivni proizvodi.
            ->where('status', $request->input('status', ProductStatus::Active->value))
            ->orderByDesc('id')
            ->paginate(config('shop.products_per_page'))
            ->withQueryString();

        return new ProductCollection($products);
    }

    public function show(Product $product): ProductDetailResource
    {
        abort_if($product->status === ProductStatus::Draft, 404);

        return new ProductDetailResource($product->load(['images', 'variants']));
    }
}
