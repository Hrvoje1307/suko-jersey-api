<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['images', 'variants'])->orderByDesc('id')->get();

        // Spec traži goli array, bez `data` omotača.
        return response()->json(
            ProductDetailResource::collection($products)->resolve($request)
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $imageUrls = Arr::pull($data, 'image_urls', []);

        $product = Product::create($data + [
            'status' => $request->validated('status', ProductStatus::Draft->value),
        ]);

        $product->syncExternalImages($imageUrls);

        return (new ProductDetailResource($product->load(['images', 'variants'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductDetailResource
    {
        $data = $request->validated();
        $imageUrls = Arr::pull($data, 'image_urls', []);

        $product->update($data);
        $product->syncExternalImages($imageUrls);

        return new ProductDetailResource($product->load(['images', 'variants']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
