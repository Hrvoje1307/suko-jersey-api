<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Procijenjena dostava
    |--------------------------------------------------------------------------
    |
    | Fiksni broj dana od kreiranja narudžbe koji se kupcu prikazuje kao
    | estimated_delivery na /orders/lookup.
    |
    */

    'estimated_delivery_days' => (int) env('SHOP_ESTIMATED_DELIVERY_DAYS', 21),

    /*
    |--------------------------------------------------------------------------
    | Paginacija kataloga
    |--------------------------------------------------------------------------
    */

    'products_per_page' => (int) env('SHOP_PRODUCTS_PER_PAGE', 15),

];
