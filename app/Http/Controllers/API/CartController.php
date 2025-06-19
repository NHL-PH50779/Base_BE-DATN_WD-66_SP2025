<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    // Xem giỏ hàng
    public function index(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $items = $cart->items()->with(['product', 'productVariant.attributeValues'])->get();
        
        return response()->json([
            'message' => 'Danh sách giỏ hàng',
            'data' => [
                'cart' => $cart,
                'items' => $items,
                'total' => $items->sum(fn($item) => $item->quantity * $item->price)
            ]
        ]);
    }

    // Thêm sản phẩm/biến thể vào giỏ hàng
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $product = Product::findOrFail($request->product_id);

        // Lấy giá từ biến thể nếu có, nếu không lấy từ sản phẩm
        $price = $request->product_variant_id
            ? ProductVariant::findOrFail($request->product_variant_id)->price
            : $product->price ?? 0;

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
                'quantity' => \DB::raw('quantity + ' . $request->quantity),
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
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
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
        $cart = Cart::where('user_id', $request->user()->id)->firstOrFail();
        $item = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Đã xóa mục khỏi giỏ hàng'
        ]);
    }
}