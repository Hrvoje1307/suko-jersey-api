<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        $topProducts = OrderItem::query()
            ->selectRaw('product_name, SUM(quantity) as units_sold')
            ->groupBy('product_name')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'product_name' => $row->product_name,
                'units_sold' => (int) $row->units_sold,
            ]);

        return response()->json([
            'total_orders_this_month' => Order::where('created_at', '>=', $monthStart)->count(),
            'revenue_this_month' => (float) Order::where('created_at', '>=', $monthStart)->sum('total_price'),
            'top_products' => $topProducts,
        ]);
    }
}
