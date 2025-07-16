<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {
        try {
            // Tổng số đơn hàng
            $totalOrders = Order::count();
            
            // Tổng số sản phẩm
            $totalProducts = Product::count();
            
            // Tổng số danh mục
            $totalCategories = Category::count();
            
            // Tổng số người dùng
            $totalUsers = User::count();
            
            // Thống kê đơn hàng theo trạng thái
            $ordersByStatus = Order::select('order_status_id', DB::raw('count(*) as count'))
                ->groupBy('order_status_id')
                ->get()
                ->map(function ($item) {
                    $statusNames = [
                        1 => 'Chờ xác nhận',
                        2 => 'Đã xác nhận', 
                        3 => 'Đang giao',
                        4 => 'Đã giao',
                        5 => 'Đã hủy'
                    ];
                    
                    return [
                        'status_id' => $item->order_status_id,
                        'status_name' => $statusNames[$item->order_status_id] ?? 'Không xác định',
                        'count' => $item->count
                    ];
                });
            
            // Tổng doanh thu (tính cả đơn đã xác nhận)
            $totalRevenue = Order::whereIn('order_status_id', [2, 3, 4])->sum('total');
            
            // Đơn hàng trong tháng này
            $ordersThisMonth = Order::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            
            // Doanh thu trong tháng này (tính cả đơn đã xác nhận)
            $revenueThisMonth = Order::whereIn('order_status_id', [2, 3, 4])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total');
            
            // Doanh thu theo từng tháng trong năm
            $monthlyRevenue = [];
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            for ($i = 1; $i <= 12; $i++) {
                $revenue = Order::whereIn('order_status_id', [2, 3, 4])
                    ->whereMonth('created_at', $i)
                    ->whereYear('created_at', now()->year)
                    ->sum('total');
                    
                $monthlyRevenue[] = [
                    'month' => $months[$i - 1],
                    'revenue' => (int) $revenue
                ];
            }

            return response()->json([
                'message' => 'Thống kê dashboard',
                'data' => [
                    'totals' => [
                        'orders' => $totalOrders,
                        'products' => $totalProducts,
                        'categories' => $totalCategories,
                        'users' => $totalUsers,
                        'revenue' => $totalRevenue
                    ],
                    'orders_by_status' => $ordersByStatus,
                    'this_month' => [
                        'orders' => $ordersThisMonth,
                        'revenue' => $revenueThisMonth
                    ],
                    'monthly_revenue' => $monthlyRevenue
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy thống kê',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}