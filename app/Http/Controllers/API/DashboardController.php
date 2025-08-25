<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\Brand;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $period = $request->get('period', '30days');
        
        // Xác định khoảng thời gian
        switch ($period) {
            case '7days':
                $start = now()->subDays(7);
                break;
            case '30days':
                $start = now()->subDays(30);
                break;
            case '3months':
                $start = now()->subMonths(3);
                break;
            case '6months':
                $start = now()->subMonths(6);
                break;
            case '1year':
                $start = now()->subYear();
                break;
            default:
                $start = Carbon::parse($startDate);
        }
        
        $end = $request->has('end_date') ? Carbon::parse($endDate) : now();
        
        // 1. Thống kê tổng quan
        $overview = $this->getOverviewStats($start, $end);
        
        // 2. Doanh thu theo thời gian
        $revenueChart = $this->getRevenueChart($start, $end, $period);
        
        // 3. Sản phẩm bán chạy
        $topProducts = $this->getTopProducts($start, $end);
        
        // 4. Danh mục bán chạy
        $topCategories = $this->getTopCategories($start, $end);
        
        // 5. Top khách hàng
        $topCustomers = $this->getTopCustomers($start, $end);
        
        // 6. Tồn kho thấp
        $lowStockProducts = $this->getLowStockProducts();
        
        // 7. Đơn hàng gần đây
        $recentOrders = $this->getRecentOrders();
        
        // 8. Tỉ lệ đơn hàng
        $orderStatusRatio = $this->getOrderStatusRatio($start, $end);
        
        // 9. Thống kê theo thương hiệu
        $topBrands = $this->getTopBrands($start, $end);
        
        return response()->json([
            'message' => 'Thống kê dashboard',
            'period' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'period' => $period
            ],
            'data' => [
                'overview' => $overview,
                'revenue_chart' => $revenueChart,
                'top_products' => $topProducts,
                'top_categories' => $topCategories,
                'top_customers' => $topCustomers,
                'low_stock_products' => $lowStockProducts,
                'recent_orders' => $recentOrders,
                'order_status_ratio' => $orderStatusRatio,
                'top_brands' => $topBrands
            ]
        ]);
    }
    
    private function getOverviewStats($start, $end)
    {
        $totalOrders = Order::whereBetween('created_at', [$start, $end])->count();
        $completedOrders = Order::whereBetween('created_at', [$start, $end])
            ->where('order_status_id', 5)->count();
        $totalRevenue = Order::whereBetween('created_at', [$start, $end])
            ->where('payment_status_id', 2)->sum('total');
        $totalProducts = Product::whereNull('deleted_at')->count();
        $totalUsers = User::where('role', '!=', 'admin')->count();
        $newUsers = User::whereBetween('created_at', [$start, $end])
            ->where('role', '!=', 'admin')->count();
        $pendingOrders = Order::where('order_status_id', 1)->count();
        
        // So sánh với kỳ trước
        $prevStart = $start->copy()->sub($end->diffInDays($start), 'days');
        $prevRevenue = Order::whereBetween('created_at', [$prevStart, $start])
            ->where('payment_status_id', 2)->sum('total');
        $revenueGrowth = $prevRevenue > 0 ? (($totalRevenue - $prevRevenue) / $prevRevenue) * 100 : 0;
        
        return [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'total_revenue' => $totalRevenue,
            'revenue_growth' => round($revenueGrowth, 2),
            'total_products' => $totalProducts,
            'total_users' => $totalUsers,
            'new_users' => $newUsers,
            'pending_orders' => $pendingOrders,
            'completion_rate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0
        ];
    }
    
    private function getRevenueChart($start, $end, $period)
    {
        $format = $period === '1year' ? '%Y-%m' : '%Y-%m-%d';
        
        $revenue = Order::select(
                DB::raw("DATE_FORMAT(created_at, '$format') as period"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status_id', 2)
            ->groupBy('period')
            ->orderBy('period')
            ->get();
            
        return $revenue->map(function($item) {
            return [
                'period' => $item->period,
                'revenue' => (float) $item->revenue,
                'orders' => (int) $item->orders
            ];
        });
    }
    
    private function getTopProducts($start, $end, $limit = 10)
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.payment_status_id', 2)
            ->select(
                'products.id',
                'products.name',
                'products.thumbnail',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.thumbnail')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getTopCategories($start, $end, $limit = 8)
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.payment_status_id', 2)
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getTopCustomers($start, $end, $limit = 10)
    {
        return DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.payment_status_id', 2)
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('SUM(orders.total) as total_spent')
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('total_spent', 'desc')
            ->limit($limit)
            ->get();
    }
    
    private function getLowStockProducts($threshold = 10)
    {
        return DB::table('product_variants')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('product_variants.stock', '<=', $threshold)
            ->where('product_variants.is_active', true)
            ->whereNull('products.deleted_at')
            ->select(
                'products.id',
                'products.name',
                'products.thumbnail',
                'product_variants.id as variant_id',
                'product_variants.Name as variant_name',
                'product_variants.stock'
            )
            ->orderBy('product_variants.stock', 'asc')
            ->limit(15)
            ->get();
    }
    
    private function getRecentOrders($limit = 10)
    {
        return Order::with(['user:id,name,email', 'items.product:id,name'])
            ->where('order_status_id', 1)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'user' => $order->user,
                    'total' => $order->total,
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at,
                    'payment_method' => $order->payment_method
                ];
            });
    }
    
    private function getOrderStatusRatio($start, $end)
    {
        $statuses = [
            1 => 'Chờ xác nhận',
            2 => 'Đã xác nhận',
            3 => 'Đang vận chuyển',
            4 => 'Đã giao hàng',
            5 => 'Hoàn thành',
            6 => 'Đã hủy'
        ];
        
        $orderStats = Order::select('order_status_id', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('order_status_id')
            ->get()
            ->keyBy('order_status_id');
            
        $total = $orderStats->sum('count');
        
        $result = [];
        foreach ($statuses as $id => $name) {
            $count = $orderStats->get($id)->count ?? 0;
            $result[] = [
                'status_id' => $id,
                'status_name' => $name,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0
            ];
        }
        
        return $result;
    }
    
    private function getTopBrands($start, $end, $limit = 8)
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.payment_status_id', 2)
            ->select(
                'brands.id',
                'brands.name',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
            )
            ->groupBy('brands.id', 'brands.name')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }
}