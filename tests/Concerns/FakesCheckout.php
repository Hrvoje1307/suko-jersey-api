<?php

namespace Tests\Concerns;

use App\Services\Payments\CheckoutGateway;
use Tests\Fakes\FakeCheckoutGateway;

/**
 * Svaki POST /orders otvara Checkout sesiju, pa svaki test koji kreira
 * narudžbu mora podmetnuti fake — inače gađa pravi Stripe.
 */
trait FakesCheckout
{
    protected FakeCheckoutGateway $checkout;

    protected function fakeCheckout(): FakeCheckoutGateway
    {
        $this->checkout = new FakeCheckoutGateway;
        $this->app->instance(CheckoutGateway::class, $this->checkout);

        return $this->checkout;
    }
}
