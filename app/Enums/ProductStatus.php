<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case SoldOut = 'sold_out';
    case Draft = 'draft';
}
