<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsService
{
    public function trackWishlistAction(int $userId, int $productId, string $action): void
    {
        DB::table('analytics_events')->insert([
            'event_type' => 'wishlist_action',
            'user_id' => $userId,
            'product_id' => $productId,
            'action' => $action, // 'add' or 'remove'
            'metadata' => json_encode([
                'timestamp' => now()->toISOString(),
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip()
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Update real-time counters
        $this->updateWishlistCounters($productId, $action);
    }

    private function updateWishlistCounters(int $productId, string $action): void
    {
        $key = "product_wishlist_count:{$productId}";
        $increment = $action === 'add' ? 1 : -1;
        
        if (Cache::has($key)) {
            Cache::increment($key, $increment);
        } else {
            // Initialize from database
            $count = DB::table('wishlists')->where('product_id', $productId)->count();
            Cache::put($key, max(0, $count + $increment), 3600);
        }
    }

    public function getWishlistAnalytics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        return [
            'total_wishlist_actions' => DB::table('analytics_events')
                ->where('event_type', 'wishlist_action')
                ->where('created_at', '>=', $startDate)
                ->count(),
                
            'daily_wishlist_actions' => DB::table('analytics_events')
                ->where('event_type', 'wishlist_action')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
                
            'top_wishlisted_products' => DB::table('analytics_events')
                ->join('products', 'analytics_events.product_id', '=', 'products.id')
                ->where('event_type', 'wishlist_action')
                ->where('action', 'add')
                ->where('analytics_events.created_at', '>=', $startDate)
                ->selectRaw('products.name, COUNT(*) as wishlist_count')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('wishlist_count')
                ->limit(10)
                ->get()
        ];
    }

    public function getProductWishlistCount(int $productId): int
    {
        $key = "product_wishlist_count:{$productId}";
        
        return Cache::remember($key, 3600, function () use ($productId) {
            return DB::table('wishlists')->where('product_id', $productId)->count();
        });
    }
}