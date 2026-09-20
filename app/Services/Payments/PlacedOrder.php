<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Rezultat kreiranja narudžbe: zapis u bazi (još neplaćen) i URL Checkouta na
 * koji frontend redirecta kupca.
 */
readonly class PlacedOrder
{
    public function __construct(
        public Order $order,
        public string $checkoutUrl,
    ) {}
}
