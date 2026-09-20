<?php

namespace App\Http\Requests;

/**
 * Spec $ref-a CreateProductRequest za PUT, pa je izmjena full replace
 * s istim obaveznim poljima.
 */
class UpdateProductRequest extends StoreProductRequest
{
    /**
     * Izostavljena opcionalna polja se brišu, a ne zadržavaju — inače PUT
     * ne bi bio replace nego merge.
     *
     * `status` je namjerna iznimka: izostavljanjem se zadržava trenutni status,
     * da izmjena proizvoda ne bi tiho skinula aktivan proizvod u draft.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $data = array_merge([
            'season' => null,
            'description' => null,
            'model_3d_url' => null,
            'images' => [],
        ], parent::validated());

        return is_null($key) ? $data : data_get($data, $key, $default);
    }
}
