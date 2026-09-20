<?php

namespace App\Enums;

/**
 * Kome je dres namijenjen. Zamjenjuje raniji `audience` (kids/men/women/unisex)
 * — muški, ženski i unisex kroj se prema dobavljaču ionako ne razlikuju.
 */
enum ProductType: string
{
    case Kids = 'kids';
    case Adult = 'adult';
}
