<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FlashSalePurchaseController extends Controller
{
    // Kiểm tra và mua sản phẩm flash sale
    public function purchase(Request $request)
    {
        $request->validate([
            'flash_sale_item_id' => 'required|exists:flash_sale_items,id',
            'quantity' => 'required|integer|min:1'
        ]);

        return DB::transaction(function () use ($request) {
            $flashSaleItem = FlashSaleItem::with('flashSale')
                ->lockForUpdate()
                ->findOrFail($request->flash_sale_item_id);

            // Kiểm tra flash sale còn active không
            $now = Carbon::now();
            $flashSale = $flashSaleItem->flashSale;
            
            if (!$flashSale->is_active) {
                return response()->json([
                    'message' => 'Flash sale không còn hoạt động'
                ], 400);
            }

            if ($now < $flashSale->start_time) {
                return response()->json([
                    'message' => 'Flash sale chưa bắt đầu'
                ], 400);
            }

            if ($now > $flashSale->end_time) {
                return response()->json([
                    'message' => 'Flash sale đã kết thúc'
                ], 400);
            }

            // Kiểm tra số lượng còn lại
            $remainingQuantity = $flashSaleItem->quantity_limit - $flashSaleItem->sold_quantity;
            
            if ($remainingQuantity < $request->quantity) {
                return response()->json([
                    'message' => "Chỉ còn {$remainingQuantity} sản phẩm",
                    'remaining_quantity' => $remainingQuantity
                ], 400);
            }

            // Cập nhật số lượng đã bán
            $flashSaleItem->increment('sold_quantity', $request->quantity);
            
            // Clear cache để cập nhật dữ liệu mới
            \Illuminate\Support\Facades\Cache::forget('current_flash_sale');
            \Illuminate\Support\Facades\Cache::forget("flash_sale_product_{$flashSaleItem->product_id}");

            // Tính tổng tiền theo giá flash sale
            $totalPrice = $flashSaleItem->sale_price * $request->quantity;

            return response()->json([
                'message' => 'Đặt hàng thành công',
                'data' => [
                    'flash_sale_item_id' => $flashSaleItem->id,
                    'product_name' => $flashSaleItem->product->name,
                    'quantity' => $request->quantity,
                    'unit_price' => $flashSaleItem->sale_price,
                    'total_price' => $totalPrice,
                    'discount_saved' => ($flashSaleItem->original_price - $flashSaleItem->sale_price) * $request->quantity
                ]
            ]);
        });
    }

    // Kiểm tra tính hợp lệ của giá flash sale
    public function validateFlashPrice(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric'
        ]);

        $now = Carbon::now();
        
        // Tìm flash sale item đang active
        $flashSaleItem = FlashSaleItem::whereHas('flashSale', function ($query) use ($now) {
            $query->where('is_active', true)
                  ->where('start_time', '<=', $now)
                  ->where('end_time', '>=', $now);
        })
        ->where('product_id', $request->product_id)
        ->where('is_active', true)
        ->first();

        if (!$flashSaleItem) {
            return response()->json([
                'is_valid' => false,
                'message' => 'Sản phẩm không trong flash sale'
            ]);
        }

        $isValidPrice = abs($request->price - $flashSaleItem->sale_price) < 0.01;

        return response()->json([
            'is_valid' => $isValidPrice,
            'flash_price' => $flashSaleItem->sale_price,
            'original_price' => $flashSaleItem->original_price,
            'message' => $isValidPrice ? 'Giá hợp lệ' : 'Giá không đúng với flash sale'
        ]);
    }
}