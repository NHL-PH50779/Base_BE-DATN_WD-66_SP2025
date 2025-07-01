<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FlashSaleService
{
    public function getCurrentFlashSale()
    {
        return Cache::remember('current_flash_sale', 300, function () {
            return FlashSale::with(['activeItems.product'])
                ->active()
                ->current()
                ->first();
        });
    }

    public function purchaseFlashSaleItem(int $flashSaleItemId, int $quantity): array
    {
        return DB::transaction(function () use ($flashSaleItemId, $quantity) {
            $item = FlashSaleItem::with('flashSale')->lockForUpdate()->findOrFail($flashSaleItemId);
            
            // Kiểm tra flash sale còn active không
            if (!$item->flashSale->is_current) {
                throw new \Exception('Flash sale đã kết thúc');
            }

            // Kiểm tra số lượng còn lại
            if ($item->remaining_quantity < $quantity) {
                throw new \Exception('Không đủ số lượng sản phẩm');
            }

            // Cập nhật số lượng đã bán
            $item->increment('sold_quantity', $quantity);

            // Xóa cache
            $this->clearFlashSaleCache();

            return [
                'success' => true,
                'item' => $item,
                'quantity_purchased' => $quantity,
                'total_price' => $item->sale_price * $quantity
            ];
        });
    }

    public function clearFlashSaleCache(): void
    {
        Cache::forget('current_flash_sale');
        Cache::forget('upcoming_flash_sale');
        
        // Clear product-specific cache
        $productIds = FlashSaleItem::pluck('product_id')->unique();
        foreach ($productIds as $productId) {
            Cache::forget("flash_sale_product_{$productId}");
        }
    }

    public function getFlashSaleCountdown(): ?array
    {
        $currentFlashSale = $this->getCurrentFlashSale();
        
        if (!$currentFlashSale) {
            // Kiểm tra flash sale sắp tới
            $upcomingFlashSale = Cache::remember('upcoming_flash_sale', 300, function () {
                return FlashSale::active()->upcoming()->orderBy('start_time')->first();
            });

            if ($upcomingFlashSale) {
                return [
                    'type' => 'upcoming',
                    'name' => $upcomingFlashSale->name,
                    'start_time' => $upcomingFlashSale->start_time,
                    'countdown' => $upcomingFlashSale->start_time->diffInSeconds(Carbon::now())
                ];
            }

            return null;
        }

        return [
            'type' => 'current',
            'name' => $currentFlashSale->name,
            'end_time' => $currentFlashSale->end_time,
            'countdown' => $currentFlashSale->time_remaining
        ];
    }
}