<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminOrderIndexRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderAdminResource;
use App\Models\Order;
use App\Notifications\OrderStatusChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class AdminOrderController extends Controller
{
    public function index(AdminOrderIndexRequest $request): JsonResponse
    {
        $orders = Order::with(['customer', 'items.productPlayer', 'items.variant.product'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
            ->orderByDesc('id')
            ->get();

        // Spec traži goli array, bez `data` omotača.
        return response()->json(
            OrderAdminResource::collection($orders)->resolve($request)
        );
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $newStatus = OrderStatus::from($request->validated('status'));
        $statusChanged = $order->status !== $newStatus;

        $order->status = $newStatus;

        if ($request->has('tracking_number_internal')) {
            $order->tracking_number_internal = $request->validated('tracking_number_internal');
        }

        $order->save();

        if ($statusChanged) {
            Notification::route('mail', $order->customer->email)
                ->notify(new OrderStatusChanged($order));
        }

        return response()->json(
            (new OrderAdminResource($order->load(['customer', 'items.productPlayer', 'items.variant.product'])))->resolve($request)
        );
    }
}
