<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeCheckoutGateway implements CheckoutGateway
{
    public function __construct(private StripeClient $stripe) {}

    public function createSession(Order $order): CheckoutSession
    {
        $order->loadMissing(['customer', 'items.product']);

        try {
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'payment',
                'client_reference_id' => $order->order_reference,
                'customer_email' => $order->customer->email,
                'line_items' => $order->items->map(
                    fn (OrderItem $item) => $this->lineItem($item)
                )->all(),
                'metadata' => ['order_reference' => $order->order_reference],
                // Bez metadate na payment intentu `payment_intent.payment_failed`
                // nema nikakvu poveznicu natrag na narudžbu.
                'payment_intent_data' => [
                    'metadata' => ['order_reference' => $order->order_reference],
                ],
                'success_url' => $this->successUrl($order),
                'cancel_url' => (string) config('services.stripe.cancel_url'),
            ]);
        } catch (ApiErrorException $e) {
            throw new CheckoutSessionFailed(
                "Stripe nije otvorio sesiju za {$order->order_reference}: {$e->getMessage()}",
                previous: $e,
            );
        }

        return new CheckoutSession($session->id, $session->url);
    }

    /**
     * @return array<string, mixed>
     */
    protected function lineItem(OrderItem $item): array
    {
        $data = [
            'name' => "{$item->product->name} ({$item->size})",
        ];

        if ($label = $item->printLabel()) {
            $data['description'] = "Tisak: {$label}";
        }

        return [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => (string) config('services.stripe.currency'),
                // `decimal:2` cast vraća string, a goli (int) cast na umnošku
                // zna odrezati cent (79.50 * 100 => 7949).
                'unit_amount' => (int) round(((float) $item->price_at_purchase) * 100),
                'product_data' => $data,
            ],
        ];
    }

    protected function successUrl(Order $order): string
    {
        return rtrim((string) config('services.stripe.success_url'), '/')
            .'/'.$order->order_reference;
    }
}
