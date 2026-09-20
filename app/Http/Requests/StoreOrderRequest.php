<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            // Veličina mora biti jedna od onih koje proizvod nudi — vidi after().
            'items.*.size' => ['required', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // Tisak je uvijek slobodan upis; gotove liste igrača frontend vuče
            // s vanjskog API-ja, pa ih backend ne poznaje ni ne provjerava.
            'items.*.custom_player_name' => ['nullable', 'string', 'max:255'],
            'items.*.custom_player_number' => ['nullable', 'string', 'max:10'],

            'shipping_address' => ['required', 'array'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
            'shipping_address.country' => ['required', 'string', 'size:2'],
        ];
    }

    /**
     * Veličina i dostupnost ovise o proizvodu, pa se provjeravaju tek nakon
     * osnovne validacije — jednim upitom za sve stavke.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $items = array_filter((array) $this->input('items'), 'is_array');

                if ($items === []) {
                    return;
                }

                $products = Product::whereIn('id', array_filter(array_column($items, 'product_id')))
                    ->get()
                    ->keyBy('id');

                foreach ($items as $index => $item) {
                    $product = $products->get($item['product_id'] ?? null);

                    // Nepostojeći proizvod je već prijavilo `exists` pravilo.
                    if (! $product) {
                        continue;
                    }

                    // Draft nije javno vidljiv, pa se ne smije ni naručiti.
                    if ($product->status === ProductStatus::Draft) {
                        $validator->errors()->add(
                            "items.{$index}.product_id",
                            'Ovaj proizvod trenutno nije u prodaji.',
                        );

                        continue;
                    }

                    if (is_string($item['size'] ?? null) && ! $product->hasSize($item['size'])) {
                        $validator->errors()->add(
                            "items.{$index}.size",
                            sprintf('Veličina %s nije dostupna za ovaj proizvod.', $item['size']),
                        );
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('shipping_address.country'))) {
            $this->merge([
                'shipping_address' => array_merge($this->input('shipping_address'), [
                    'country' => strtoupper($this->input('shipping_address.country')),
                ]),
            ]);
        }
    }
}
