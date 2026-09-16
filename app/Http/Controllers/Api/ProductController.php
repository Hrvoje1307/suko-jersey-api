<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductDetailResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request): ProductCollection
    {
        $products = Product::query()
            // `variants` se učitava nefiltrirano i kad je zadan `size` — kartica
            // mora ponuditi sve veličine proizvoda, ne samo filtriranu. Eager
            // load je i jedino što drži listu na konstantnom broju upita.
            ->with(['images', 'variants'])
            ->when($request->input('category'), fn ($q, $v) => $q->where('category', $v))
            // Djelomično i case-insensitive (`ilike` na Postgresu) — frontend
            // šalje slobodan tekst, pa `Real` mora naći `Real Madrid`.
            ->when($request->input('club'), fn ($q, $v) => $q->whereLike('club_or_team', "%{$v}%", caseSensitive: false))
            ->when($request->input('kit_type'), fn ($q, $v) => $q->where('kit_type', $v))
            ->when($request->input('audience'), fn ($q, $v) => $q->where('audience', $v))
            ->when($request->input('season'), fn ($q, $v) => $q->where('season', $v))
            ->when($request->input('personalization'), fn ($q, $v) => $q->where('personalization', $v))
            // Filtrira po postojanju varijante, ne po zalihi — rasprodana
            // veličina se i dalje smije naći u katalogu.
            ->when($request->input('size'), fn ($q, $v) => $q->whereHas('variants', fn ($vq) => $vq->where('size', $v)))
            // Bez eksplicitnog filtera javno se vraćaju samo aktivni proizvodi.
            ->where('status', $request->input('status', ProductStatus::Active->value))
            ->when(
                $request->input('sort'),
                fn ($q, $sort) => $q->orderBy($sort, $request->input('direction', 'desc'))
            )
            // Default poredak i tiebreaker za jednake vrijednosti sortiranja.
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', config('shop.products_per_page')))
            ->withQueryString();

        return new ProductCollection($products);
    }

    /**
     * Distinct vrijednosti za filtere koje nemaju enum — klub i sezona.
     * Bez ovoga ih frontend mora hardkodirati i raziđu se s bazom.
     */
    public function filters(): JsonResponse
    {
        $active = fn () => Product::query()->where('status', ProductStatus::Active->value);

        return response()->json([
            'clubs' => $active()
                ->whereNotNull('club_or_team')
                ->where('club_or_team', '!=', '')
                ->distinct()
                ->orderBy('club_or_team')
                ->pluck('club_or_team')
                ->all(),
            // Najnovija sezona prva — frontend ih tako i prikazuje.
            'seasons' => $active()
                ->whereNotNull('season')
                ->where('season', '!=', '')
                ->distinct()
                ->orderByDesc('season')
                ->pluck('season')
                ->all(),
        ]);
    }

    public function show(Product $product): ProductDetailResource
    {
        abort_if($product->status === ProductStatus::Draft, 404);

        return new ProductDetailResource($product->load(['images', 'variants', 'players']));
    }
}
