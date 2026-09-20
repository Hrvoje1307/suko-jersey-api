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

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::orderByDesc('id')->get();

        // Spec traži goli array, bez `data` omotača.
        return response()->json(
            ProductDetailResource::collection($products)->resolve($request)
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated() + [
            'status' => $request->validated('status', ProductStatus::Draft->value),
            'images' => [],
        ]);

        return (new ProductDetailResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductDetailResource
    {
        $product->update($request->validated());

        return new ProductDetailResource($product);
    }

    /**
     * `order_items.product_id` je NOT NULL FK bez ON DELETE — naručen proizvod
     * se zato ne smije obrisati jer bi to odnijelo i povijest narudžbi. Za
     * povlačenje iz ponude služi status `sold_out` ili `draft`.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Proizvod se nalazi u narudžbama i ne može se obrisati. Postavi status na "sold_out" ili "draft".',
            ], 409);
        }

        $product->delete();

        return response()->json(null, 204);
    }
}
