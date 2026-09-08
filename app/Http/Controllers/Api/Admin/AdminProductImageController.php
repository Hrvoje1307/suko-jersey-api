<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImagesRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class AdminProductImageController extends Controller
{
    public function store(StoreProductImagesRequest $request, Product $product): JsonResponse
    {
        $sortOrder = (int) $product->images()->max('sort_order');

        $created = collect($request->file('images'))->map(function ($file) use ($product, &$sortOrder) {
            return $product->images()->create([
                'path' => $file->store("products/{$product->id}", 'public'),
                'sort_order' => ++$sortOrder,
            ]);
        });

        // Prva slika proizvoda automatski postaje primarna.
        $product->ensurePrimaryImage();

        return response()->json([
            'images' => $created->map(fn ($image) => [
                'url' => $image->url(),
                'sort_order' => $image->sort_order,
                'is_primary' => $image->fresh()->is_primary,
            ])->all(),
        ], 201);
    }
}
