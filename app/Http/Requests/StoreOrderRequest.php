<?php

namespace App\Http\Requests;

use App\Enums\Personalization;
use App\Models\Product;
use App\Models\ProductVariant;
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
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // Personalizacija: dopušteno ovisi o products.personalization — vidi after().
            'items.*.product_player_id' => ['nullable', 'integer', 'exists:product_players,id'],
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
     * Pravila personalizacije ovise o proizvodu iza varijante, pa se provjeravaju
     * tek nakon osnovne validacije — jednim upitom za sve stavke.
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

                $variants = ProductVariant::with('product.players')
                    ->whereIn('id', array_filter(array_column($items, 'product_variant_id')))
                    ->get()
                    ->keyBy('id');

                foreach ($items as $index => $item) {
                    $variant = $variants->get($item['product_variant_id'] ?? null);

                    // Nepostojeću varijantu je već prijavilo `exists` pravilo.
                    if (! $variant || ! $variant->product) {
                        continue;
                    }

                    $this->validatePersonalization($validator, (int) $index, $variant->product, $item);
                }
            },
        ];
    }

    /**
     * Pravila po vrijednosti products.personalization:
     *  - none         -> ni odabir s liste ni slobodan upis
     *  - preset_only  -> obavezan odabir s liste, bez slobodnog upisa
     *  - custom_text  -> obavezan upis imena, bez odabira s liste
     *  - both         -> smije jedno od dvoje (ili ništa), nikad oboje
     *
     * custom_player_number nije pokriven tim pravilima (ni DB CHECK-om), pa ga
     * vežemo uz custom_player_name: dopušten je samo ondje gdje je dopušten i
     * slobodan upis imena, i nikad uz odabir s gotove liste.
     *
     * @param  array<string, mixed>  $item
     */
    protected function validatePersonalization(
        Validator $validator,
        int $index,
        Product $product,
        array $item,
    ): void {
        $personalization = $product->personalization ?? Personalization::None;

        $presetId = $item['product_player_id'] ?? null;
        $hasPreset = filled($presetId);
        $hasCustomName = filled($item['custom_player_name'] ?? null);
        $hasCustomNumber = filled($item['custom_player_number'] ?? null);

        $presetKey = "items.{$index}.product_player_id";
        $nameKey = "items.{$index}.custom_player_name";
        $numberKey = "items.{$index}.custom_player_number";

        $notAllowed = $personalization === Personalization::None
            ? 'Ovaj proizvod ne dopušta personalizaciju.'
            : 'Ovaj proizvod ne dopušta taj oblik personalizacije.';

        if ($hasPreset && ! $personalization->allowsPreset()) {
            $validator->errors()->add($presetKey, $notAllowed);
        }

        if ($hasCustomName && ! $personalization->allowsCustomText()) {
            $validator->errors()->add($nameKey, $notAllowed);
        }

        // Slobodan broj ide isključivo uz slobodno ime — gotovi igrač s liste
        // nosi vlastiti broj, pa bi uz njega poslan broj bio proturječan.
        if ($hasCustomNumber && ! $personalization->allowsCustomText()) {
            $validator->errors()->add($numberKey, $notAllowed);
        } elseif ($hasCustomNumber && $hasPreset) {
            $validator->errors()->add($numberKey, 'Igrač s liste već ima svoj broj, pa se broj ne upisuje zasebno.');
        }

        if ($personalization === Personalization::PresetOnly && ! $hasPreset) {
            $validator->errors()->add($presetKey, 'Za ovaj proizvod je obavezan odabir igrača s liste.');
        }

        if ($personalization === Personalization::CustomText && ! $hasCustomName) {
            $validator->errors()->add($nameKey, 'Za ovaj proizvod je obavezan upis imena igrača.');
        }

        if ($personalization === Personalization::Both && $hasPreset && $hasCustomName) {
            $validator->errors()->add($presetKey, 'Moguć je ili odabir s liste ili slobodan upis imena, ne oboje.');
        }

        // `exists` pravilo hvata nepostojećeg igrača, ali ne i igrača s tuđeg proizvoda.
        if ($hasPreset && $personalization->allowsPreset()
            && ! $product->players->contains('id', (int) $presetId)) {
            $validator->errors()->add($presetKey, 'Odabrani igrač ne pripada ovom proizvodu.');
        }
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
