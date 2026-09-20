<?php

namespace Tests\Fakes;

use App\Models\Order;
use App\Services\Payments\CheckoutGateway;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\CheckoutSessionFailed;

/**
 * Stripe zamjena za testove — bilježi narudžbe kojima je sesija otvorena i
 * na zahtjev simulira pad pružatelja plaćanja.
 */
class FakeCheckoutGateway implements CheckoutGateway
{
    /** @var array<int, Order> */
    public array $sessionsFor = [];

    public bool $shouldFail = false;

    public function createSession(Order $order): CheckoutSession
    {
        if ($this->shouldFail) {
            throw new CheckoutSessionFailed('Stripe nedostupan (test).');
        }

        $this->sessionsFor[] = $order;

        return new CheckoutSession(
            id: 'cs_test_'.$order->order_reference,
            url: 'https://checkout.stripe.com/c/pay/cs_test_'.$order->order_reference,
        );
    }
}
