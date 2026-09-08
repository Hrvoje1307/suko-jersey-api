<?php

namespace App\Enums;

enum Audience: string
{
    case Kids = 'kids';
    case Men = 'men';
    case Women = 'women';
    case Unisex = 'unisex';
}
