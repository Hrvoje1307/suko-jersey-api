<?php

namespace App\Http\Requests;

use App\Enums\Audience;
use App\Enums\KitType;
use App\Enums\Personalization;
use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', Rule::enum(ProductCategory::class)],
            'club' => ['sometimes', 'string', 'max:255'],
            'kit_type' => ['sometimes', Rule::enum(KitType::class)],
            'audience' => ['sometimes', Rule::enum(Audience::class)],
            'season' => ['sometimes', 'string', 'max:50'],
            'personalization' => ['sometimes', Rule::enum(Personalization::class)],
            // Namjerno bez fiksne liste: pored S–XXL postoje i dječje veličine
            // (128, 140). Nepoznata veličina zato daje 200 s praznim `data`,
            // a ne 422 — katalog ne smije puknuti na krivom filteru.
            'size' => ['sometimes', 'nullable', 'string', 'max:50'],
            // Draft se nikad ne izlaže javno.
            'status' => ['sometimes', Rule::enum(ProductStatus::class)->only([
                ProductStatus::Active,
                ProductStatus::SoldOut,
            ])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,48'],
            // Whitelista jer vrijednost ide direktno u ORDER BY.
            'sort' => ['sometimes', Rule::in(['created_at', 'price', 'name'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
