<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     * Optimized: Combines 8 queries into 2 using raw aggregation.
     * Cached for 5 minutes to reduce database load.
     */
    public function stats(): JsonResponse
    {
        $stats = cache()->remember('admin.dashboard.stats', 300, function () {
            $startOfMonth = now()->startOfMonth();
            
            // Single query to get all order-related stats (was 6 separate queries)
            $orderStats = DB::table('orders')
                ->selectRaw("
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as orders_this_month,
                    SUM(CASE WHEN status != 'cancelled' AND created_at >= ? THEN total ELSE 0 END) as revenue_this_month
                ", [$startOfMonth, $startOfMonth])
                ->first();
            
            // Single query to get product and customer counts (was 3 separate queries)
            $otherStats = DB::selectOne("
                SELECT 
                    (SELECT COUNT(*) FROM products) as total_products,
                    (SELECT COUNT(*) FROM products WHERE stock_quantity < 10) as low_stock_products,
                    (SELECT COUNT(*) FROM users WHERE role != 'admin' OR role IS NULL) as total_customers
            ");
            
            return [
                'total_revenue' => (float) ($orderStats->total_revenue ?? 0),
                'total_orders' => (int) ($orderStats->total_orders ?? 0),
                'total_products' => (int) ($otherStats->total_products ?? 0),
                'total_customers' => (int) ($otherStats->total_customers ?? 0),
                'orders_this_month' => (int) ($orderStats->orders_this_month ?? 0),
                'revenue_this_month' => (float) ($orderStats->revenue_this_month ?? 0),
                'low_stock_products' => (int) ($otherStats->low_stock_products ?? 0),
                'pending_orders' => (int) ($orderStats->pending_orders ?? 0),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent orders.
     * Optimized: Added withCount to avoid N+1 on items.
     * Cached for 2 minutes.
     */
    public function recentOrders(): JsonResponse
    {
        $orders = cache()->remember('admin.dashboard.recent_orders', 120, function () {
            return Order::with('user')
                ->withCount('items')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn($order) => $order->toApiArray())
                ->toArray();
        });
        
        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get revenue chart data (last 7 days).
     * Optimized: Single GROUP BY query instead of 7 loop queries.
     * Cached for 1 hour.
     */
    public function revenueChart(): JsonResponse
    {
        $data = cache()->remember('admin.dashboard.revenue_chart', 3600, function () {
            $startDate = now()->subDays(6)->startOfDay();
            
            // Single query with GROUP BY (was 7 queries in a loop)
            $revenueData = Order::where('status', '!=', Order::STATUS_CANCELLED)
                ->where('created_at', '>=', $startDate)
                ->selectRaw("DATE(created_at) as date, SUM(total) as revenue")
                ->groupByRaw("DATE(created_at)")
                ->pluck('revenue', 'date')
                ->toArray();
            
            // Build complete 7-day array with zero-fills for missing days
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dateKey = $date->format('Y-m-d');
                $data[] = [
                    'date' => $date->format('M d'),
                    'revenue' => (float) ($revenueData[$dateKey] ?? 0),
                ];
            }
            
            return $data;
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get low stock products.
     * Cached for 5 minutes.
     */
    public function lowStockProducts(): JsonResponse
    {
        $products = cache()->remember('admin.dashboard.low_stock', 300, function () {
            return Product::where('stock_quantity', '<', 10)
                ->orderBy('stock_quantity')
                ->take(10)
                ->get()
                ->map(fn($product) => $product->toApiArray())
                ->toArray();
        });
        
        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }
}
