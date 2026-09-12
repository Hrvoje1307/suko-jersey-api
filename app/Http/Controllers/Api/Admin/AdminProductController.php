<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['images', 'variants', 'players'])->orderByDesc('id')->get();

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

        return (new ProductDetailResource($product->load(['images', 'variants', 'players'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductDetailResource
    {
        $data = $request->validated();
        $imageUrls = Arr::pull($data, 'image_urls', []);

        $product->update($data);
        $product->syncExternalImages($imageUrls);

        return new ProductDetailResource($product->load(['images', 'variants', 'players']));
    }

    /**
     * Brisanje kaskadno ruši varijante, a order_items.product_variant_id je
     * NOT NULL FK bez ON DELETE — naručen proizvod se zato ne smije obrisati
     * jer bi to odnijelo i povijest narudžbi. Za povlačenje iz ponude služi
     * status `sold_out` ili `draft`.
     */
    public function destroy(Product $product): JsonResponse
    {
        $isOrdered = OrderItem::query()
            ->whereIn('product_variant_id', $product->variants()->select('id'))
            ->exists();

        if ($isOrdered) {
            return response()->json([
                'message' => 'Proizvod se nalazi u narudžbama i ne može se obrisati. Postavi status na "sold_out" ili "draft".',
            ], 409);
        }

        $product->delete();

        return response()->json(null, 204);
    }
}
