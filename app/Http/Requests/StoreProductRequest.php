<?php

namespace App\Http\Requests;

use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'club_or_team' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ProductCategory::class)],
            'type' => ['required', Rule::enum(ProductType::class)],
            'season' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['nullable', 'string'],
            // Bez fiksne liste: pored S–XXL postoje i dječje brojčane (128, 140).
            // Sve navedeno je uvijek dostupno — zalihe nema.
            'sizes' => ['required', 'array', 'min:1', 'max:20'],
            'sizes.*' => ['required', 'string', 'max:10', 'distinct'],
            // Redoslijed je značajan: prva slika je primarna.
            'images' => ['sometimes', 'array', 'max:20'],
            'images.*' => ['required', 'url', 'max:500', 'distinct'],
            // Jedan 3D model po dresu, ali više slika.
            'model_3d_url' => ['nullable', 'url', 'max:500'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
        ];
    }
}
