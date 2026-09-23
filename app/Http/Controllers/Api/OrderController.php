<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderLookupRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderConfirmationResource;
use App\Http\Resources\OrderPaymentStatusResource;
use App\Http\Resources\OrderStatusPublicResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\CheckoutSessionFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        try {
            $placed = $orders->create($request->validated());
        } catch (CheckoutSessionFailed $e) {
            Log::error('Checkout sesija nije otvorena', ['exception' => $e]);

            return response()->json([
                'message' => 'Plaćanje trenutno nije dostupno. Pokušaj ponovno za koji trenutak.',
            ], 502);
        }

        return (new OrderConfirmationResource($placed->order, $placed->checkoutUrl))
            ->response()
            ->setStatusCode(201);
    }

    public function lookup(OrderLookupRequest $request): OrderStatusPublicResource
    {
        $order = Order::query()
            ->where('order_reference', $request->validated('order_reference'))
            // Email se sprema kako ga je kupac upisao na checkoutu, a na
            // praćenju ga upiše kako god — velika slova ne smiju dati 404.
            ->whereHas('customer', fn ($q) => $q->whereRaw(
                'lower(email) = ?',
                [mb_strtolower($request->validated('email'))],
            ))
            ->firstOrFail();

        return new OrderStatusPublicResource($order);
    }

    /**
     * Status plaćanja samo po referenci — koristi ga stranica na koju Stripe
     * vrati kupca, dok čeka webhook potvrdu. Referenca je pogodiva, pa ovdje
     * NE ide ništa osobno: ni email, ni adresa, ni iznos, ni tracking.
     */
    public function paymentStatus(string $reference): OrderPaymentStatusResource
    {
        $order = Order::query()
            ->where('order_reference', $reference)
            ->firstOrFail();

        return new OrderPaymentStatusResource($order);
    }
}
