<?php

namespace App\Services;

use App\Enums\OrderStatus;
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
     * @param  array{customer: array{email: string, name: string, phone?: string|null}, items: array<int, array{product_variant_id: int, quantity: int}>, shipping_address: array{line1: string, line2?: string|null, city: string, postal_code: string, country: string}}  $data
     */
    public function create(array $data): Order
    {
        $variants = ProductVariant::with('product')
            ->whereIn('id', array_column($data['items'], 'product_variant_id'))
            ->get()
            ->keyBy('id');

        $lines = $this->buildLines($data['items'], $variants);

        $total = array_sum(array_map(
            fn (array $line) => $line['unit_price'] * $line['quantity'],
            $lines
        ));

        $order = DB::transaction(function () use ($data, $lines, $total) {
            $order = Order::create([
                'order_reference' => $this->generateReference(),
                'customer_name' => $data['customer']['name'],
                'customer_email' => $data['customer']['email'],
                'customer_phone' => $data['customer']['phone'] ?? null,
                'shipping_line1' => $data['shipping_address']['line1'],
                'shipping_line2' => $data['shipping_address']['line2'] ?? null,
                'shipping_city' => $data['shipping_address']['city'],
                'shipping_postal_code' => $data['shipping_address']['postal_code'],
                'shipping_country' => $data['shipping_address']['country'],
                'status' => OrderStatus::Ordered,
                'total_price' => $total,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        Notification::route('mail', $order->customer_email)
            ->notify(new OrderPlaced($order));

        return $order;
    }

    /**
     * Validira zalihu i pretvara stavke u snapshot retke za order_items.
     * Zaliha se ne dekrementira — admin je održava ručno.
     *
     * @param  array<int, array{product_variant_id: int, quantity: int}>  $items
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
                'product_name' => $variant->product->name,
                'size' => $variant->size,
                'unit_price' => $variant->product->price,
                'quantity' => $item['quantity'],
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
