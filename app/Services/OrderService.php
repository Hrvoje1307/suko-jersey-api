<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\Payments\CheckoutGateway;
use App\Services\Payments\PlacedOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private CheckoutGateway $checkout) {}

    /**
     * @param  array{customer: array{email: string, name: string, phone?: string|null}, items: array<int, array{product_id: int, size: string, quantity: int, custom_player_name?: string|null, custom_player_number?: string|null}>, shipping_address: array{line1: string, line2?: string|null, city: string, postal_code: string, country: string}}  $data
     */
    public function create(array $data): PlacedOrder
    {
        $products = Product::whereIn('id', array_column($data['items'], 'product_id'))
            ->get()
            ->keyBy('id');

        $lines = $this->buildLines($data['items'], $products);

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
                // Narudžba nastaje neplaćena; `status` je fulfillment os i
                // ostaje `ordered`, plaćanje potvrđuje tek Stripe webhook.
                'payment_status' => PaymentStatus::Unpaid,
                'total_price' => $total,
            ]);

            $order->items()->createMany($lines);

            return $order;
        });

        // Mrežni poziv namjerno ide izvan transakcije — inače bi Stripeova
        // latencija držala lockove na orders/customers.
        $session = $this->checkout->createSession($order);

        $order->update(['stripe_checkout_session_id' => $session->id]);

        // OrderPlaced mail NE ide odavde: šalje ga webhook kad plaćanje prođe,
        // inače bi svaki napušteni checkout dobio potvrdu narudžbe.
        return new PlacedOrder($order, $session->url);
    }

    /**
     * Gradi retke za order_items. Cijena se snapshota u trenutku kupnje, a uz
     * nju i veličina — proizvod je kasnije smije prestati nuditi, a narudžba
     * mora ostati čitljiva.
     *
     * Zaliha se ne prati: sve navedene veličine su uvijek dostupne, a
     * nedostupna se miče iz `products.sizes`.
     *
     * @param  array<int, array{product_id: int, size: string, quantity: int, custom_player_name?: string|null, custom_player_number?: string|null}>  $items
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    protected function buildLines(array $items, Collection $products): array
    {
        $errors = [];
        $lines = [];

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                $errors["items.{$index}.product_id"] = ['Odabrani proizvod ne postoji.'];

                continue;
            }

            $lines[] = [
                'product_id' => $product->id,
                // StoreOrderRequest je već potvrdio da proizvod nudi ovu veličinu.
                'size' => $item['size'],
                'price_at_purchase' => $product->price,
                'quantity' => $item['quantity'],
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
