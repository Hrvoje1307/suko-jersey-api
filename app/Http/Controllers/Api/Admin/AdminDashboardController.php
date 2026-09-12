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

        // order_items nema snapshot naziva, pa se do imena ide kroz varijantu.
        $topProducts = OrderItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
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
            'revenue_this_month' => (float) Order::where('created_at', '>=', $monthStart)->sum('total_price'),
            'top_products' => $topProducts,
        ]);
    }
}
