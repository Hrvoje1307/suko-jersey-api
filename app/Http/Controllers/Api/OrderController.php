<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderLookupRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderConfirmationResource;
use App\Http\Resources\OrderStatusPublicResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->create($request->validated());

        return (new OrderConfirmationResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function lookup(OrderLookupRequest $request): OrderStatusPublicResource
    {
        $order = Order::query()
            ->where('order_reference', $request->validated('order_reference'))
            ->where('customer_email', $request->validated('email'))
            ->firstOrFail();

        return new OrderStatusPublicResource($order);
    }
}
