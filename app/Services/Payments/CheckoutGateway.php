<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Sloj iznad pružatelja plaćanja — postoji da se u testovima može zamijeniti
 * fakeom, bez mockanja ugniježđenih Stripe servisa.
 */
interface CheckoutGateway
{
    /**
     * @throws CheckoutSessionFailed
     */
    public function createSession(Order $order): CheckoutSession;
}
