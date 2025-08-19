<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    // Xem giỏ hàng
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'message' => 'Giỏ hàng trống',
                'data' => ['items' => [], 'total' => 0]
            ]);
        }
        
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $items = $cart->items()->with(['product', 'productVariant'])->get();
        
        // Transform items to include stock info
        $transformedItems = $items->map(function ($item) {
            $itemArray = $item->toArray();
            if ($item->productVariant) {
                $itemArray['product_variant']['stock'] = $item->productVariant->stock;
            }
            return $itemArray;
        });
        
        return response()->json([
            'message' => 'Danh sách giỏ hàng',
            'data' => [
                'cart' => $cart,
                'items' => $transformedItems,
                'total' => $items->sum(fn($item) => $item->quantity * $item->price)
            ]
        ]);
    }

    // Thêm sản phẩm/biến thể vào giỏ hàng
    public function store(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $product = Product::findOrFail($request->product_id);

        // Lấy giá từ request hoặc từ biến thể/sản phẩm
        $price = $request->price ?? (
            $request->product_variant_id
                ? ProductVariant::findOrFail($request->product_variant_id)->price
                : ($product->price ?? 0)
        );

        // Kiểm tra tồn kho
        if ($request->product_variant_id) {
            $variant = ProductVariant::findOrFail($request->product_variant_id);
            if ($variant->stock < $request->quantity) {
                return response()->json([
                    'message' => 'Số lượng tồn kho không đủ'
                ], 400);
            }
        }

        $item = $cart->items()->updateOrCreate(
            [
                'product_id' => $request->product_id,
                'product_variant_id' => $request->product_variant_id,
            ],
            [
                'quantity' => DB::raw('quantity + ' . $request->quantity),
                'price' => $price
            ]
        );

        return response()->json([
            'message' => 'Đã thêm vào giỏ hàng',
            'data' => $item->load(['product', 'productVariant'])
        ], 201);
    }

    // Cập nhật số lượng mục trong giỏ hàng
    public function update(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);

        // Kiểm tra tồn kho
        if ($item->product_variant_id) {
            $variant = ProductVariant::findOrFail($item->product_variant_id);
            if ($variant->stock < $request->quantity) {
                return response()->json([
                    'message' => 'Số lượng tồn kho không đủ'
                ], 400);
            }
        }

        $item->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Đã cập nhật giỏ hàng',
            'data' => $item->load(['product', 'productVariant'])
        ]);
    }

    // Xóa mục khỏi giỏ hàng
    public function destroy(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['message' => 'Vui lòng đăng nhập'], 401);
        }
        
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Đã xóa mục khỏi giỏ hàng'
        ]);
    }

    // ===== ADMIN METHODS =====

    // Xem tất cả giỏ hàng (Admin)
    public function adminIndex()
    {
        $carts = Cart::with(['user', 'items.product', 'items.productVariant'])
            ->whereHas('items')
            ->get();

        return response()->json([
            'message' => 'Danh sách giỏ hàng',
            'data' => $carts
        ]);
    }

    // Xem chi tiết giỏ hàng của user (Admin)
    public function adminShow($userId)
    {
        $cart = Cart::with(['user', 'items.product', 'items.productVariant'])
            ->where('user_id', $userId)
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Không tìm thấy giỏ hàng',
                'data' => ['items' => [], 'total' => 0]
            ]);
        }

        return response()->json([
            'message' => 'Chi tiết giỏ hàng',
            'data' => [
                'cart' => $cart,
                'items' => $cart->items,
                'total' => $cart->items->sum(fn($item) => $item->quantity * $item->price)
            ]
        ]);
    }
}