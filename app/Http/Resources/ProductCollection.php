<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public $collects = ProductSummaryResource::class;

    /**
     * Spec traži točno { data, meta: { current_page, total_pages, total_items } }.
     * Laravel default dodaje `links` i koristi `last_page`/`total`.
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'total_pages' => $paginated['last_page'],
                'total_items' => $paginated['total'],
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->all();
    }
}
