<?php

namespace App\Http\Requests;

use App\Enums\Audience;
use App\Enums\KitType;
use App\Enums\ProductCategory;
use App\Enums\ProductStatus;
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
            'kit_type' => ['required', Rule::enum(KitType::class)],
            'audience' => ['required', Rule::enum(Audience::class)],
            'season' => ['nullable', 'string', 'max:255'],
            // Jedan 3D model po dresu, ali više slika.
            'model_3d_url' => ['nullable', 'url', 'max:2048'],
            'image_urls' => ['sometimes', 'array', 'max:20'],
            'image_urls.*' => ['required', 'url', 'max:2048', 'distinct'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
        ];
    }
}
