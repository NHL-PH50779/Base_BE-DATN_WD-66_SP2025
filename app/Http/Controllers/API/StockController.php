<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class StockController extends Controller
{
    // Kiểm tra stock realtime cho sản phẩm
    public function checkStock(Request $request, $productId)
    {
        $product = Product::with('variants')->find($productId);
        
        if (!$product) {
            return response()->json(['message' => 'Sản phẩm không tồn tại'], 404);
        }

        $variantId = $request->get('variant_id');
        $quantity = $request->get('quantity', 1);

        if ($variantId) {
            $variant = $product->variants->where('id', $variantId)->first();
            if (!$variant) {
                return response()->json(['message' => 'Phiên bản không tồn tại'], 404);
            }

            $available = $variant->stock >= $quantity;
            return response()->json([
                'available' => $available,
                'current_stock' => $variant->stock,
                'requested_quantity' => $quantity,
                'variant_id' => $variantId,
                'message' => $available ? 'Có thể mua' : 'Không đủ hàng'
            ]);
        }

        // Tổng stock tất cả variants
        $totalStock = $product->variants->sum('stock');
        $available = $totalStock >= $quantity;

        return response()->json([
            'available' => $available,
            'total_stock' => $totalStock,
            'variants' => $product->variants->map(function($v) {
                return [
                    'id' => $v->id,
                    'name' => $v->Name,
                    'stock' => $v->stock,
                    'price' => $v->price
                ];
            }),
            'requested_quantity' => $quantity,
            'message' => $available ? 'Có thể mua' : 'Không đủ hàng'
        ]);
    }

    // API cho frontend kiểm tra trước khi add to cart
    public function validatePurchase(Request $request)
    {
        $productId = $request->get('product_id');
        $variantId = $request->get('variant_id');
        $quantity = $request->get('quantity', 1);

        if (!$productId) {
            return response()->json(['message' => 'Thiếu product_id'], 400);
        }

        $product = Product::find($productId);
        if (!$product || !$product->is_active) {
            return response()->json([
                'valid' => false,
                'message' => 'Sản phẩm không khả dụng'
            ], 400);
        }

        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            if (!$variant || !$variant->is_active) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Phiên bản sản phẩm không khả dụng'
                ], 400);
            }

            if ($variant->stock < $quantity) {
                return response()->json([
                    'valid' => false,
                    'message' => "Chỉ còn {$variant->stock} sản phẩm trong kho",
                    'available_stock' => $variant->stock
                ], 400);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Có thể mua',
                'available_stock' => $variant->stock,
                'price' => $variant->price
            ]);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Có thể mua',
            'total_stock' => $product->variants->sum('stock')
        ]);
    }
}