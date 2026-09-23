<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Javni endpointi
|--------------------------------------------------------------------------
*/

Route::get('products', [ProductController::class, 'index']);
// Mora ostati iznad products/{product}, inače wildcard proguta `filters`.
Route::get('products/filters', [ProductController::class, 'filters']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::post('orders', [OrderController::class, 'store']);
// Mora ostati iznad orders/{reference}/status iz istog razloga kao products/filters.
// Vraća datum i tracking, pa i ovdje ide limit protiv pogađanja referenci.
Route::get('orders/lookup', [OrderController::class, 'lookup'])
    ->middleware('throttle:30,1');
// Pogodivo samo referencom, pa ide uz eksplicitan limit — API grupa nema throttleApi().
Route::get('orders/{reference}/status', [OrderController::class, 'paymentStatus'])
    ->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| Bez throttlea — Stripe ponavlja dostave i 429 bi ga držao u retry petlji.
| Autentikacija je potpis u Stripe-Signature zaglavlju, ne token.
|
*/

Route::post('webhooks/stripe', StripeWebhookController::class);

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

Route::post('admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('products', [AdminProductController::class, 'index']);
    Route::post('products', [AdminProductController::class, 'store']);
    Route::put('products/{product}', [AdminProductController::class, 'update']);
    Route::delete('products/{product}', [AdminProductController::class, 'destroy']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::put('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('dashboard', [AdminDashboardController::class, 'index']);
});
