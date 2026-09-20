<?php

namespace App\Services\Payments;

/**
 * Minimum koji nam treba od pružatelja plaćanja: id sesije (da webhook zna
 * koju narudžbu gađa) i URL na koji frontend redirecta kupca.
 */
readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
