<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'variants' => ['present', 'array'],
            'variants.*.size' => ['required', 'string', 'max:10', 'distinct'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
