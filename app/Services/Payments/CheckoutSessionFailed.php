<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Pružatelj plaćanja nije uspio otvoriti sesiju. Narudžba u tom slučaju ostaje
 * u bazi kao `unpaid` — ne brišemo je, jer je trag pokušaja koristan.
 */
class CheckoutSessionFailed extends RuntimeException {}
