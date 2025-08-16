<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\FlashSaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $items = $cart->items()->with(['product', 'productVariant'])->orderBy('created_at', 'desc')->get();
        
        \Log::info('Cart items for user ' . $user->id . ': ' . $items->count()); // Debug log
        
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
            'quantity' => 'required|integer|min:1|max:100',
            'price' => 'nullable|numeric|min:0'
        ]);
        
        // Debug log
        \Log::info('Cart store request:', $request->all());

        if ($validator->fails()) {
            \Log::error('Cart validation failed:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $product = Product::findOrFail($request->product_id);

        // Kiểm tra xem có phải Flash Sale không (dựa vào giá khác với giá gốc)
        $flashSaleItem = FlashSaleItem::where('product_id', $request->product_id)
            ->whereHas('flashSale', function($q) {
                $q->where('is_active', true)
                  ->where('start_time', '<=', now())
                  ->where('end_time', '>=', now());
            })
            ->where('is_active', true)
            ->first();
            
        // Nếu có giá trong request và khác giá gốc thì là Flash Sale
        $isFlashSalePurchase = $flashSaleItem && $request->price && $request->price == $flashSaleItem->sale_price;
        
        // Lấy giá từ request hoặc từ biến thể/sản phẩm
        $price = $request->price ?? (
            $request->product_variant_id
                ? ProductVariant::findOrFail($request->product_variant_id)->price
                : ($product->price ?? 0)
        );

        // Kiểm tra sản phẩm đã có trong giỏ hàng (xử lý null variant)
        $existingItem = $cart->items()->where('product_id', $request->product_id)
            ->where(function($query) use ($request) {
                if ($request->product_variant_id) {
                    $query->where('product_variant_id', $request->product_variant_id);
                } else {
                    $query->whereNull('product_variant_id');
                }
            })->first();
        
        $currentQuantityInCart = $existingItem ? $existingItem->quantity : 0;
        $totalQuantityAfterAdd = $currentQuantityInCart + $request->quantity;
        
        // Nếu đã có sản phẩm Flash Sale trong giỏ hàng với cùng giá, không cho thêm nữa
        if ($isFlashSalePurchase && $existingItem && $existingItem->price == $flashSaleItem->sale_price) {
            return response()->json([
                'message' => 'Bạn đã sở hữu sản phẩm Flash Sale này! Mỗi khách hàng chỉ được mua 1 lần để đảm bảo công bằng cho tất cả khách hàng.'
            ], 400);
        }
        
        // Kiểm tra tồn kho
        if ($isFlashSalePurchase) {
            // Kiểm tra Flash Sale stock (chỉ cho phép mua 1 sản phẩm Flash Sale)
            if ($flashSaleItem->remaining_quantity <= 0) {
                return response()->json([
                    'message' => 'Sản phẩm Flash Sale đã hết hàng'
                ], 400);
            }
            if ($request->quantity > 1) {
                return response()->json([
                    'message' => 'Mỗi khách hàng chỉ được mua 1 sản phẩm Flash Sale'
                ], 400);
            }
        } elseif ($request->product_variant_id) {
            $variant = ProductVariant::findOrFail($request->product_variant_id);
            if ($totalQuantityAfterAdd > $variant->stock) {
                return response()->json([
                    'message' => "Bạn đã có {$currentQuantityInCart} sản phẩm trong giỏ hàng không thể thêm số lượng sản phẩm đã chọn vì vượt quá {$variant->stock} số lượng sản phẩm trong kho"
                ], 400);
            }
        } else {
            // Kiểm tra stock của product chính
            if ($totalQuantityAfterAdd > $product->stock) {
                return response()->json([
                    'message' => "Bạn đã có {$currentQuantityInCart} sản phẩm trong giỏ hàng không thể thêm số lượng sản phẩm đã chọn vì vượt quá {$product->stock} số lượng sản phẩm trong kho"
                ], 400);
            }
        }

        try {
            if ($existingItem) {
                // Nếu đã có, cập nhật số lượng
                $existingItem->quantity += $request->quantity;
                $existingItem->price = $price; // Cập nhật giá mới nhất
                $existingItem->save();
                $item = $existingItem;
            } else {
                // Nếu chưa có, tạo mới
                $item = $cart->items()->create([
                    'product_id' => $request->product_id,
                    'product_variant_id' => $request->product_variant_id,
                    'quantity' => $request->quantity,
                    'price' => $price
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Cart item creation failed:', ['error' => $e->getMessage(), 'request' => $request->all()]);
            return response()->json([
                'message' => 'Lỗi khi thêm vào giỏ hàng: ' . $e->getMessage()
            ], 500);
        }
        
        // Cập nhật Flash Sale sold_quantity nếu là Flash Sale
        if ($isFlashSalePurchase) {
            $success = $flashSaleItem->incrementSoldQuantity($request->quantity);
            if (!$success) {
                // Rollback cart item nếu Flash Sale hết hàng
                $item->delete();
                return response()->json([
                    'message' => 'Sản phẩm Flash Sale đã hết hàng'
                ], 400);
            }
            // Clear cache để cập nhật dữ liệu Flash Sale
            \Cache::forget('current_flash_sale');
        }
        // Không kiểm tra stock cho sản phẩm thường khi thêm vào giỏ hàng
        // Chỉ kiểm tra khi checkout

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
        
        // Kiểm tra nếu là Flash Sale item thì giảm sold_quantity
        $flashSaleItem = FlashSaleItem::where('product_id', $item->product_id)
            ->whereHas('flashSale', function($q) {
                $q->where('is_active', true)
                  ->where('start_time', '<=', now())
                  ->where('end_time', '>=', now());
            })
            ->where('is_active', true)
            ->first();
            
        if ($flashSaleItem && $item->price == $flashSaleItem->sale_price) {
            // Giảm sold_quantity
            $flashSaleItem->decrementSoldQuantity($item->quantity);
            // Clear cache
            \Cache::forget('current_flash_sale');
        }
        
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
