<?php

namespace App\Services;

use App\Models\Wishlist;
use Illuminate\Support\Facades\Cache;

class WishlistCacheService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'wishlist:user:';

    public function getUserWishlist(int $userId)
    {
        $cacheKey = self::CACHE_PREFIX . $userId;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return Wishlist::with(['product.brand', 'product.category'])
                ->where('user_id', $userId)
                ->latest()
                ->get();
        });
    }

    public function invalidateUserWishlist(int $userId): void
    {
        $cacheKey = self::CACHE_PREFIX . $userId;
        Cache::forget($cacheKey);
        Cache::forget("wishlist:count:{$userId}");
    }

    public function getWishlistCount(int $userId): int
    {
        $cacheKey = "wishlist:count:{$userId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return Wishlist::where('user_id', $userId)->count();
        });
    }

    public function isProductInWishlist(int $userId, int $productId): bool
    {
        $cacheKey = "wishlist:check:{$userId}:{$productId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $productId) {
            return Wishlist::where('user_id', $userId)
                ->where('product_id', $productId)
                ->exists();
        });
    }
}