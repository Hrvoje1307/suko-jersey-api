<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderLookupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_reference' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
