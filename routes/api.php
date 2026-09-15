<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\AdminProductImageController;
use App\Http\Controllers\Api\Admin\AdminProductPlayerController;
use App\Http\Controllers\Api\Admin\AdminProductVariantController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
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
Route::get('orders/lookup', [OrderController::class, 'lookup']);

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
    Route::post('products/{product}/images', [AdminProductImageController::class, 'store']);
    Route::put('products/{product}/variants', [AdminProductVariantController::class, 'update']);
    Route::put('products/{product}/players', [AdminProductPlayerController::class, 'update']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::put('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('dashboard', [AdminDashboardController::class, 'index']);
});
