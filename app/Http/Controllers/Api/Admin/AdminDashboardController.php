<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        // order_items nema snapshot naziva, pa se do imena ide kroz proizvod.
        $topProducts = OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('products.name as product_name, SUM(order_items.quantity) as units_sold')
            ->groupBy('products.name')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'product_name' => $row->product_name,
                'units_sold' => (int) $row->units_sold,
            ]);

        return response()->json([
            'total_orders_this_month' => Order::where('created_at', '>=', $monthStart)->count(),
            // Samo plaćeno — napušteni Stripe checkouti ostaju u tablici
            // kao `unpaid` i ne smiju napuhati prihod.
            'revenue_this_month' => (float) Order::where('created_at', '>=', $monthStart)
                ->where('payment_status', PaymentStatus::Paid)
                ->sum('total_price'),
            'top_products' => $topProducts,
        ]);
    }
}
