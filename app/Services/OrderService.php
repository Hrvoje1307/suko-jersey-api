<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Notifications\OrderPlaced;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * @param  array{customer: array{email: string, name: string, phone?: string|null}, items: array<int, array{product_variant_id: int, quantity: int, product_player_id?: int|null, custom_player_name?: string|null, custom_player_number?: string|null}>, shipping_address: array{line1: string, line2?: string|null, city: string, postal_code: string, country: string}}  $data
     */
    public function create(array $data): Order
    {
        $variants = ProductVariant::with('product')
            ->whereIn('id', array_column($data['items'], 'product_variant_id'))
            ->get()
            ->keyBy('id');

        $lines = $this->buildLines($data['items'], $variants);

        $total = array_sum(array_map(
            fn (array $line) => $line['price_at_purchase'] * $line['quantity'],
            $lines
        ));

        $order = DB::transaction(function () use ($data, $lines, $total) {
            // Kupci se prepoznaju po emailu (unique index); podaci zadnje
            // narudžbe prepisuju ranije upisano ime i telefon.
            $customer = Customer::updateOrCreate(
                ['email' => $data['customer']['email']],
                [
                    'name' => $data['customer']['name'],
                    'phone' => $data['customer']['phone'] ?? null,
                ],
            );

            $order = Order::create([
                'order_reference' => $this->generateReference(),
                'customer_id' => $customer->id,
                'shipping_address_line1' => $data['shipping_address']['line1'],
                'shipping_address_line2' => $data['shipping_address']['line2'] ?? null,
                'shipping_city' => $data['shipping_address']['city'],
                'shipping_postal_code' => $data['shipping_address']['postal_code'],
                'shipping_country' => $data['shipping_address']['country'],
                'status' => OrderStatus::Ordered,
                'total_price' => $total,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        Notification::route('mail', $order->customer->email)
            ->notify(new OrderPlaced($order));

        return $order;
    }

    /**
     * Validira zalihu i gradi retke za order_items.
     * Zaliha se ne dekrementira — admin je održava ručno.
     *
     * @param  array<int, array{product_variant_id: int, quantity: int, product_player_id?: int|null, custom_player_name?: string|null, custom_player_number?: string|null}>  $items
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<int, array<string, mixed>>
     */
    protected function buildLines(array $items, $variants): array
    {
        $errors = [];
        $lines = [];

        foreach ($items as $index => $item) {
            $variant = $variants->get($item['product_variant_id']);

            if (! $variant) {
                $errors["items.{$index}.product_variant_id"] = ['Odabrana varijanta ne postoji.'];

                continue;
            }

            if ($variant->stock_quantity < $item['quantity']) {
                $errors["items.{$index}.quantity"] = [sprintf(
                    'Nema dovoljno zalihe za veličinu %s (dostupno: %d).',
                    $variant->size,
                    $variant->stock_quantity
                )];

                continue;
            }

            $lines[] = [
                'product_variant_id' => $variant->id,
                'price_at_purchase' => $variant->product->price,
                'quantity' => $item['quantity'],
                // Personalizaciju je StoreOrderRequest već uskladio s products.personalization.
                'product_player_id' => $item['product_player_id'] ?? null,
                'custom_player_name' => $item['custom_player_name'] ?? null,
                'custom_player_number' => $item['custom_player_number'] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'ORD-'.Str::upper(Str::random(5));
        } while (Order::where('order_reference', $reference)->exists());

        return $reference;
    }
}
